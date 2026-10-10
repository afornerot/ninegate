#!/bin/bash
set -e

# ============================================================
# Chargement de la configuration depuis .env et .env.local
# - .env est copié au build (figé dans l'image)
# - .env.local est monté en volume à runtime (surcharge)
# - .env.local écrase les valeurs de .env
# ============================================================

set -a
source /app/.env 2>/dev/null || true
if [ -f /app/.env.local ]; then
    source /app/.env.local 2>/dev/null || true
fi
set +a

# Deuxième passe : interpolation des références ${VAR} dans les valeurs
# (ex: LDAP_USERSDN=ou=users,${LDAP_BASEDN})
TMP_ENV=$(mktemp)
printenv > "$TMP_ENV"
envsubst < "$TMP_ENV" > "${TMP_ENV}.resolved"
while IFS='=' read -r key value; do
    case "$key" in
        ''|\#*) continue ;;
    esac
    export "$key=$value"
done < "${TMP_ENV}.resolved"
rm -f "$TMP_ENV" "${TMP_ENV}.resolved"

# ============================================================
# Bootstrap initial : génère la config slapd et charge le LDIF
# Toujours réinitialisé au démarrage pour rester cohérent avec .env/.env.local
# Les données persistent dans le volume mais la config est reconstruite
# ============================================================

echo "[openldap] Initialisation de l'annuaire LDAP..."

# Vérification des variables minimales
: "${LDAP_BASEDN:?LDAP_BASEDN doit être défini}"
: "${LDAP_USERSDN:?LDAP_USERSDN doit être défini}"
: "${LDAP_GROUPSDN:?LDAP_GROUPSDN doit être défini}"
: "${LDAP_WRITER_DN:?LDAP_WRITER_DN doit être défini}"
: "${LDAP_WRITER_PASSWORD:?LDAP_WRITER_PASSWORD doit être défini}"
: "${APP_ADMIN:?APP_ADMIN doit être défini (utilisé par template-db.ldif)}"

# Nettoyage systématique de la config (la BDD reste persistée)
rm -rf /etc/openldap/slapd.d/*
chown -R ldap:ldap /etc/openldap/slapd.d /var/lib/openldap

# Interpolation des templates
envsubst < /opt/openldap/template-slapd.conf > /etc/openldap/slapd.conf
envsubst < /opt/openldap/template-db.ldif > /tmp/bootstrap.ldif

# Génération du hash SSHA du password writer
WRITER_HASH=$(slappasswd -h "{SSHA}" -s "${LDAP_WRITER_PASSWORD}")
sed -i "s|__WRITER_HASH__|${WRITER_HASH}|g" /etc/openldap/slapd.conf
sed -i "s|__WRITER_HASH__|${WRITER_HASH}|g" /tmp/bootstrap.ldif

# Génération du hash du root admin cn=config
CONFIG_ROOT_HASH=$(slappasswd -h "{SSHA}" -s "${LDAP_WRITER_PASSWORD}")
sed -i "s|__CONFIG_ROOT_HASH__|${CONFIG_ROOT_HASH}|g" /etc/openldap/slapd.conf

# Conversion slapd.conf → slapd.d
slaptest -f /etc/openldap/slapd.conf -F /etc/openldap/slapd.d

# Chargement du bootstrap initial (n'efface pas la BDD existante)
slapadd -F /etc/openldap/slapd.d -l /tmp/bootstrap.ldif 2>/dev/null || echo "[openldap] Bootstrap déjà chargé (ignoré)"

# Fix permissions
chown -R ldap:ldap /etc/openldap/slapd.d /var/lib/openldap

echo "[openldap] Annuaire initialisé : baseDN=${LDAP_BASEDN}, writer=${LDAP_WRITER_DN}"

# ============================================================
# Lancement de slapd en foreground
# ============================================================

# S'assurer que slapd (utilisateur ldap) peut lire/écrire ses volumes
chown -R ldap:ldap /etc/openldap/slapd.d /var/lib/openldap 2>/dev/null || true

exec slapd -h "ldap:///" -u ldap -g ldap -F /etc/openldap/slapd.d -d "${LDAP_DEBUG_LEVEL:-256}"