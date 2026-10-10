<?php

namespace App\Service;

/**
 * Normalisation des valeurs pour stockage dans un annuaire LDAP.
 *
 * - cn (Common Name) : caractères de RDN interdits (, + " \ < > ; = #) filtrés
 * - uid : alphanumériques + . - _ uniquement
 * - memberUID : même contrainte que uid
 *
 * Indépendant de UsernameNormalizer (ninegate) — applique des règles
 * spécifiques LDAP plus restrictives.
 */
class LdapNormalizer
{
    /**
     * Normalise une valeur pour utilisation comme uid LDAP ou memberUID.
     */
    public function normalizeForUid(string $raw): string
    {
        $value = mb_strtolower(trim($raw), 'UTF-8');
        $value = preg_replace('/[^a-z0-9._-]/u', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        $value = trim($value, '_-.');
        return $value !== '' ? $value : 'user';
    }

    /**
     * Normalise une valeur pour utilisation comme cn (Common Name).
     * Conserve les espaces mais filtre les caractères RDN interdits.
     */
    public function normalizeForCn(string $raw): string
    {
        $value = preg_replace('/[,+"\\\\<>=;#]/u', '_', $raw);
        return trim($value);
    }

    /**
     * Échappe une valeur pour inclusion dans un DN (RFC 4514).
     */
    public function escapeDn(string $value): string
    {
        if (preg_match('/^ /', $value)) {
            $value = '\\ ' . substr($value, 1);
        }
        if (preg_match('/ $/', $value)) {
            $value = substr($value, 0, -1) . '\\ ';
        }
        return preg_replace('/([,+"\\\\<>;=])/', '\\\\$1', $value);
    }
}