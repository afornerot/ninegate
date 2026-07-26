# Ninegate

Portail d'accueil personnalisable avec gestion de pages, blogs, items, annonces, chartes et widgets.

## Installation

### Prérequis
- Docker & Docker Compose

### Démarrage

```bash
docker compose up -d
docker exec ninegate php bin/console app:init
```

### Accès

| Service | URL | Description |
|---------|-----|-------------|
| Application | http://localhost:8019 | Portail principal |
| PostgreSQL | localhost:5432 | Base de données |
| Adminer | http://localhost:6019 | Administration BDD |
| MailHog | http://localhost:8025 | Catch-all email |

## Authentification

L'application supporte trois modes d'authentification (configuré via la variable `APP_MODEAUTH`) :

| Mode | Description |
|------|-------------|
| `SQL` | Login/mot de passe classique |
| `CAS` | SSO via serveur CAS (phpCAS) |
| `OIDC` | OpenID Connect |

## Fonctionnalités

### Pages
Création de pages personnalisables avec un système de widgets drag-and-drop. Les pages sont accessibles par groupe ou par rôle. Chaque utilisateur peut définir une page préférée (via un coeur dans la navbar) qui sera affichée par défaut à la connexion.

### Widgets
Widgets réutilisables sur les pages :
- Notes (Markdown)
- Liens (ItemLinks)
- Blog
- Horloge
- Météo
- Flux RSS
- Galerie / Carousel
- Bureau / Favoris
- Fichiers
- Annonces
- Tâches

### Blogs
Création de blogs avec articles au format Markdown. Chaque blog peut être lié à des groupes.

### Items
Système de liens/catégories avec icônes personnalisées, accessible par groupe ou rôle.

### Annonces
Système d'annonces/catégories avec icônes et couleurs personnalisables, accessible par groupe ou rôle. Les utilisateurs peuvent masquer les annonces (si autorisé par l'admin). Widget dédié avec masquage automatique si aucune annonce n'est disponible.

### Chartes
Chartes au format Markdown avec signature obligatoire optionnelle. Les utilisateurs doivent signer les chartes avant d'accéder à l'application.

### Thèmes
Système de thèmes CSS variables configurable via l'interface admin. 14 thèmes prédéfinis.

## Architecture

- **Framework** : Symfony 7.4
- **Base de données** : PostgreSQL 17
- **ORM** : Doctrine (attributs PHP)
- **Frontend** : Bootstrap 5, jQuery, EasyMDE, Font Awesome, Select2
- **Auth** : Multi-mode (SQL/CAS/OIDC)
- **Stockage** : Local ou S3 (Flysystem)

### Gestion de la base de données

Le schéma est géré via `doctrine:schema:update --force --complete` (pas de migrations).

```bash
# Recréer le schéma
docker exec ninegate bin/console d:s:u --force --complete

# Charger les fixtures
docker exec ninegate php bin/console app:init
```

## Licence

GNU Affero General Public License v3.0 - Voir [LICENSE](LICENSE)
