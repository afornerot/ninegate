# Coffre-fort de mots de passe (vault), partagé comme Pages/Blogs/Calendriers

## Context

Ninegate n'a aucune notion de coffre-fort. Objectif : un module « Coffre-fort » où chaque coffre porte un titre, un slug, un propriétaire ou des groupes/rôles (exactement la logique de partage de Pages/Blogs/Calendriers), contient des entrées (titre, identifiant, mot de passe, URL, notes), et s'affiche sur une page via un nouveau widget `pagewidget_vault`.

Décisions produit déjà arrêtées :
- chiffrement serveur uniquement, AES-256-GCM, clé `APP_ENCRYPT_KEY` (déjà présente dans `.env`, jamais câblée jusqu'ici) ;
- structure Coffre partageable + entrées ;
- tout utilisateur ayant accès au coffre voit les mots de passe en clair (aucune distinction Viewer/User/Master) ;
- widget = entrées d'un coffre choisi en config, mot de passe masqué, boutons révéler/copier ;
- aucune fonction annexe (pas de générateur de mot de passe, pas de filtre de recherche, pas de journal d'audit).

Référence de code à copier : `src/Controller/CalendarController.php` + `src/Voter/CalendarVoter.php` + `src/Form/CalendarType.php` + `templates/calendar/{list,edit,view}.html.twig` (partage groupe/rôle + double route `/user` `/admin`).

## Approach

Ordre : le tree compile et les routes existantes restent intactes après chaque étape.

### 1. Service de chiffrement + paramètre

Créer `src/Service/VaultCipher.php` (aucun équivalent dans `src/`) :

```php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class VaultCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1:';
    private string $key;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $decoded = base64_decode((string) $parameterBag->get('appEncryptKey'), true);
        if (false === $decoded || 32 !== strlen($decoded)) {
            throw new \RuntimeException('APP_ENCRYPT_KEY doit être une clé base64 de 32 octets');
        }
        $this->key = $decoded;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if (false === $ciphertext) {
            throw new \RuntimeException('Chiffrement impossible');
        }

        return self::PREFIX.base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new \RuntimeException('Format de chiffré inconnu');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if (false === $raw || strlen($raw) < 28) {
            throw new \RuntimeException('Chiffré corrompu');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $plaintext = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $plaintext) {
            throw new \RuntimeException('Déchiffrement impossible');
        }

        return $plaintext;
    }
}
```

Dans `config/services.yaml`, sous `parameters:` (après `appSecret`), ajouter :

```yaml
  appEncryptKey: "%env(resolve:APP_ENCRYPT_KEY)%"
```

`App\` est déjà autoconfiguré (`config/services.yaml`), donc `VaultCipher` est injectable sans déclaration.

### 2. Entités

`src/Entity/Vault.php` — table `vault`, attributs Doctrine identiques en forme à `Calendar` :

| champ | mapping |
|---|---|
| `id` | `#[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null` |
| `title` | `#[ORM\Column(length: 255)] private ?string $title = null` |
| `slug` | `#[ORM\Column(length: 255, unique: true)] private ?string $slug = null` |
| `vaultOrder` | `#[ORM\Column(options: ['default' => 0])] private int $vaultOrder = 0` |
| `roles` | `#[ORM\Column(type: 'json', nullable: true)] private ?array $roles = null` |
| `groups` | `#[ORM\ManyToMany(targetEntity: Group::class)] #[ORM\JoinTable(name: 'vault_group')] private Collection $groups` |
| `entries` | `#[ORM\OneToMany(targetEntity: VaultEntry::class, mappedBy: 'vault', cascade: ['remove'], orphanRemoval: true)] private Collection $entries` |
| `user` | `#[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable: true)] private ?User $user = null` |
| `createdAt` | `#[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private ?\DateTimeInterface $createdAt = null` |

Constructeur : `$this->groups = new ArrayCollection(); $this->entries = new ArrayCollection(); $this->createdAt = new \DateTime();`
`__toString(): string` → `$this->title ?? ''` (nécessaire pour l'`EntityType` de config du widget).
Accesseurs : tous les getters/setters, plus `addEntry`/`removeEntry` (même corps que `Calendar::addEvent`/`removeEvent`) et `clearGroups()` (copier `Page::clearGroups()`, `src/Entity/Page.php:127-131`).

`src/Entity/VaultEntry.php` — table `vault_entry` :

| champ | mapping |
|---|---|
| `id` | `#[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null` |
| `title` | `#[ORM\Column(length: 255)] private ?string $title = null` |
| `login` | `#[ORM\Column(length: 255, nullable: true)] private ?string $login = null` |
| `passwordCipher` | `#[ORM\Column(name: 'password_cipher', type: 'text', nullable: true)] private ?string $passwordCipher = null` |
| `url` | `#[ORM\Column(length: 500, nullable: true)] private ?string $url = null` |
| `notes` | `#[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null` |
| `createdAt` | `#[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]` |
| `updatedAt` | `#[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]` |
| `vault` | `#[ORM\ManyToOne(targetEntity: Vault::class, inversedBy: 'entries')] #[ORM\JoinColumn(nullable: false)] private ?Vault $vault = null` |

Pas de `setPassword()`/`getPassword()` : uniquement `getPasswordCipher()`/`setPasswordCipher(?string)` pour qu'aucun template ne puisse imprimer le clair par accident. `__toString()` → `$this->title ?? ''`. Constructeur initialise `createdAt` et `updatedAt`.

Schéma : `docker exec ninegate php bin/console doctrine:schema:update --force --complete` (le projet n'utilise pas de migrations, `README.md:85`).

### 3. Repositories

`src/Repository/VaultRepository.php` (`ServiceEntityRepository<Vault>`) :

```php
public function findAccessibleVaults(?User $user): array        // array_filter(findAll(), isVaultAccessibleForUser)
public function isVaultAccessibleForUser(Vault $vault, ?User $user): bool
public function isVaultOwnerOrGroupMaster(Vault $vault, User $user): bool
public function findVaultsByGroups(array $groups): array        // innerJoin c.groups, order vaultOrder ASC
```

`isVaultAccessibleForUser` — règle exacte (l'anonyme est TOUJOURS refusé, contrairement à `CalendarRepository` qui autorise `ROLE_VISITOR` via `roles`) :

```php
if (!$user instanceof User) { return false; }
if ($vault->getUser() && $vault->getUser()->getId() === $user->getId()) { return true; }
if (!empty($vault->getRoles()) && [] !== array_intersect($user->getRoles(), $vault->getRoles())) { return true; }
foreach ($vault->getGroups() as $group) {
    if ($group->getUsers()->contains($user)) { return true; }
}
return false;
```

`isVaultOwnerOrGroupMaster` :

```php
if ($vault->getUser() && $vault->getUser()->getId() === $user->getId()) { return true; }
foreach ($vault->getGroups() as $group) {
    $ug = $group->getUserGroup($user);
    if ($ug && UserGroup::ROLE_MASTER === $ug->getRole()) { return true; }
}
return false;
```

Pas de bypass admin dans le repository : le bypass admin existe seulement sur les routes `/admin`, comme `PageVoter`/`BlogVoter`.

`src/Repository/VaultEntryRepository.php` (`ServiceEntityRepository<VaultEntry>`) :

```php
public function findByVault(Vault $vault, ?int $limit = null): array
// createQueryBuilder('e')->where('e.vault = :vault')->setParameter('vault', $vault)
//   ->orderBy('e.title', 'ASC')  + setMaxResults($limit) si $limit !== null
```

### 4. Voter

`src/Voter/VaultVoter.php`, calqué sur `src/Voter/CalendarVoter.php` :

```php
public const VIEW = 'vault_view';
public const CREATE = 'vault_create';
public const EDIT = 'vault_edit';
public const DELETE = 'vault_delete';
```

- `supports()` : `CREATE` → `true` ; `VIEW|EDIT|DELETE` → `$subject instanceof Vault`.
- `isAdminRoute()` : identique.
- `voteOnAttribute()` :
  - `$user = $token->getUser();` — `VIEW` : si `$user instanceof User && $user->hasRole('ROLE_ADMIN') && isAdminRoute()` → `true`, sinon `$this->vaultRepository->isVaultAccessibleForUser($subject, $user instanceof User ? $user : null)`.
  - `CREATE` : `$user instanceof User` (tout utilisateur authentifié crée son coffre ; ne PAS copier `CalendarVoter::canCreate` qui réserve la création à ADMIN/MASTER, sinon un ROLE_USER n'a pas de coffre personnel).
  - `EDIT`/`DELETE` : `$user instanceof User` et (`admin && isAdminRoute()` ou `isVaultOwnerOrGroupMaster`).

### 5. Slug

`src/Service/SlugService.php` : ajouter `private VaultRepository $vaultRepository` au constructeur (position après `CalendarEventRepository`) et la branche `'Vault' => $this->vaultRepository->findOneBy(['slug' => $slug]),` dans le `match` de `slugExists()`. L'autowiring Symfony couvre le nouveau paramètre ; aucun autre appelant (aucun `new SlugService` dans `src`/`tests`).

### 6. Formulaires

`src/Form/VaultType.php` — copie de `src/Form/CalendarType.php` (options `data_class`, `isAdmin`, `user`, `mode`) avec :

- toujours : `title` (TextType, label « Titre »), `vaultOrder` (IntegerType, label « Ordre », requis false).
- admin + mode `submit` : `vaultType` ChoiceType non mappé, expanded, label « Type de coffre », choix `['Coffre personnel' => 'personal', 'Coffre de groupe/role' => 'group_role']`.
- admin : `user` EntityType `User` requis false, `groups` EntityType `Group` multiple requis false, `allUsers` CheckboxType non mappé « Tout le monde », `roles` `Select2Type` multiple (Admin/Master/User).
- non-admin + `$user` : `vaultType` (submit seulement) choix `['Coffre personnel' => 'personal', 'Coffre de groupe' => 'group']`, `user` HiddenType non mappé, `groups` EntityType choix restreint aux groupes où l'utilisateur est MASTER (même filtre `UserGroup::ROLE_MASTER` que `CalendarType.php:111`).
- Aucun `submit` dans ce type : les templates utilisent un `<button type="submit" class="btn btn-success">Valider</button>` comme `templates/calendar/edit.html.twig`.

`src/Form/VaultEntryType.php` :

```php
->add('title', TextType::class, ['label' => 'Titre', 'attr' => ['class' => 'form-control']])
->add('login', TextType::class, ['label' => 'Identifiant', 'required' => false, 'attr' => ['class' => 'form-control', 'autocomplete' => 'off']])
->add('password', PasswordType::class, [
    'label' => 'Mot de passe',
    'mapped' => false,
    'required' => false,
    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
    'help' => 'Laisser vide pour conserver le mot de passe actuel.',
])
->add('url', TextType::class, ['label' => 'URL', 'required' => false, 'attr' => ['class' => 'form-control']])
->add('notes', TextareaType::class, ['label' => 'Notes', 'required' => false, 'attr' => ['class' => 'form-control', 'rows' => 4]])
```

`configureOptions` : `'data_class' => VaultEntry::class`.

### 7. Contrôleur coffres

`src/Controller/VaultController.php`, structure exacte de `CalendarController` (constantes `ROUTE_PREFIX_USER = 'app_user_vault'`, `ROUTE_PREFIX_ADMIN = 'app_admin_vault'`, `getListRoute(bool $isAdmin)`) :

| route | nom | comportement |
|---|---|---|
| `/user/vault` + `/admin/vault` | `app_user_vault_list` / `app_admin_vault_list` | admin → `findBy([], ['vaultOrder' => 'ASC'])` ; sinon `findAccessibleVaults($user)` ; render `vault/list.html.twig`, titre `Coffres-forts` / `Mes Coffres-forts` |
| `/user/vault/submit` + admin | `app_user_vault_submit` / `app_admin_vault_submit` | form `mode => 'submit'` ; slug `generateUniqueSlug($title, 'Vault')` ; normalisation partage (ci-dessous) ; flash `'Coffre-fort créé avec succès'` ; redirect `getListRoute` |
| `/user/vault/update/{id}` + admin | `app_user_vault_update` / `app_admin_vault_update` | `isGranted(VaultVoter::EDIT, $vault)` sinon redirect liste ; slug régénéré avec `$vault->getId()` en excludeId uniquement s'il change ; flash `'Coffre-fort modifié avec succès'` |
| `/user/vault/delete/{id}` + admin | `app_user_vault_delete` / `app_admin_vault_delete` | `isGranted(VaultVoter::DELETE, …)` ; `try { remove/flush } catch (\Exception $e) { addFlash('error', $e->getMessage()) }` |
| `/user/vault/view/{slug}` + admin | `app_user_vault_view` / `app_admin_vault_view` | `findOneBy(['slug' => $slug])` sinon `createNotFoundException('Coffre-fort non trouvé')` ; `isGranted(VaultVoter::VIEW, …)` sinon `createAccessDeniedException('Accès refusé')` ; render `vault/view.html.twig` avec `entries` = `findByVault($vault)` et `routeentry_submit`/`routeentry_update`/`routeentry_delete`/`routereveal` |

Normalisation du partage, appliquée à `submit` **et** `update` après `isValid()` (l'ordre compte) :

```php
$allUsers = $form->has('allUsers') && $form->get('allUsers')->getData();
if ($allUsers) {
    $vault->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER']); // pas ROLE_VISITOR : l'anonyme n'a jamais accès
    $vault->clearGroups();
    $vault->setUser(null);
} else {
    $vaultType = $form->has('vaultType') ? $form->get('vaultType')->getData() : null;
    if ('personal' === $vaultType) {
        $vault->setUser($isAdmin ? ($form->get('user')->getData() ?: $user) : $user);
    }
    if (null !== $vault->getUser()) {
        $vault->clearGroups();
        $vault->setRoles(null);
    }
}
```

### 8. Contrôleur entrées

`src/Controller/VaultEntryController.php` (dépendances : `EntityManagerInterface`, `VaultRepository`, `VaultEntryRepository`, `VaultCipher`) :

| route | nom |
|---|---|
| `/user/vault/entry/submit/{vaultId}` + `/admin/...` | `app_user_vaultentry_submit` / `app_admin_vaultentry_submit` |
| `/user/vault/entry/update/{vaultId}/{id}` + admin | `app_user_vaultentry_update` / `app_admin_vaultentry_update` |
| `/user/vault/entry/delete/{vaultId}/{id}` + admin | `app_user_vaultentry_delete` / `app_admin_vaultentry_delete` |
| `/user/vault/entry/reveal/{id}` + admin, `methods: ['POST']` | `app_user_vaultentry_reveal` / `app_admin_vaultentry_reveal` |

Règles :
- `submit`/`update`/`delete` : `$vault = $vaultRepository->find($vaultId)` (introuvable → redirect `app_{user|admin}_vault_list`), `isGranted(VaultVoter::EDIT, $vault)` sinon redirect `app_{user|admin}_vault_view` avec `['slug' => $vault->getSlug()]`. Les entrées n'ont pas de droit propre : qui édite le coffre édite ses entrées.
- `submit` : `new VaultEntry(); $entry->setVault($vault);` puis si `$form->get('password')->getData()` non vide → `$entry->setPasswordCipher($this->vaultCipher->encrypt($plain))`. Flash `'Entrée créée avec succès'` ; redirect vue du coffre.
- `update` : `$entry = $vaultEntryRepository->find($id)` et `$entry->getVault()->getId() !== $vaultId` → redirect vue du coffre. Même règle « champ mot de passe vide = on conserve `passwordCipher` » ; `$entry->setUpdatedAt(new \DateTime())`. Flash `'Entrée modifiée avec succès'`.
- `delete` : `remove`/`flush`, flash `'Entrée supprimée'`, redirect vue du coffre.
- `reveal` : `$entry = $vaultEntryRepository->find($id)` → `JsonResponse(['error' => 'Entrée introuvable'], 404)` ; `!$this->isGranted(VaultVoter::VIEW, $entry->getVault())` → `JsonResponse(['error' => 'Accès refusé'], 403)` ; `null === $entry->getPasswordCipher()` → `['password' => '']` ; sinon `try { ['password' => $vaultCipher->decrypt(...)] } catch (\RuntimeException) { JsonResponse(['error' => 'Impossible de déchiffrer le mot de passe'], 500) }`.

### 9. Templates coffres

`templates/vault/list.html.twig` — copie de `templates/calendar/list.html.twig` : bouton « Nouveau Coffre-fort » (`isAdmin ? 'app_admin_vault_submit' : 'app_user_vault_submit'`), table `id="table"` (DataTable `order: [[1, 'asc']]`) colonnes Actions / Titre / Slug / Entrées / Groupes / Rôles ; lignes conditionnées par `{% if is_granted('vault_view', vault) %}`, bouton œil → `*_vault_view` (`{slug}`), bouton crayon si `is_granted('vault_edit', vault)`.

`templates/vault/edit.html.twig` — copie de `templates/calendar/edit.html.twig` :
- barre : Valider (`<button type="submit">`), Annuler → `path(routecancel)`, Supprimer si `vault.id` (`*_vault_delete`, confirm `'Supprimer ce coffre-fort et toutes ses entrées ?'`).
- colonne gauche carte « Coffre-fort » : `form.title`, `form.vaultOrder`.
- colonne droite carte « Permissions » : même structure `vaultType` / `vault-user-wrapper` / `vault-groups-roles-wrapper` / `vault-groups-wrapper` / `vault-all-users-wrapper`.
- `{% block localscript %}` : même script de bascule que le calendrier, avec deux ajouts obligatoires :
  1. ids préfixés `vault-` (sélecteur des radios `input[name*="vaultType"]`) ;
  2. à chaque bascule, **désactiver** les champs des wrappers masqués (`wrapper.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = true; })`) et les réactiver quand le wrapper est affiché. Sans cela un `<select>` masqué par `display:none` est quand même soumis et un coffre de groupe reçoit un `user` parasite.

`templates/vault/view.html.twig` — sur le modèle de `templates/calendar/view.html.twig` :
- boutons : « Nouvelle entrée » si `is_granted('vault_edit', vault)` → `path(routeentry_submit, {vaultId: vault.id})` ; « Modifier le coffre-fort » ; « Retour » vers la liste.
- table `id="table"` (DataTable) des entrées, lignes `<tr class="vault-entry-row" data-entry-id="{{ entry.id }}">` :
  - cellule mot de passe : `<span class="vault-password" data-hidden="1">••••••</span>` + deux boutons :
    ```twig
    <button type="button" class="btn btn-sm btn-outline-secondary vault-reveal-btn"
            data-entry-id="{{ entry.id }}" data-reveal-url="{{ path(routereveal, {id: entry.id}) }}" title="Afficher">
        <i class="fas fa-eye"></i>
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary vault-copy-btn"
            data-entry-id="{{ entry.id }}" data-reveal-url="{{ path(routereveal, {id: entry.id}) }}" title="Copier">
        <i class="fas fa-copy"></i>
    </button>
    ```
  - colonnes : Titre / Identifiant / Mot de passe / URL (`<a href target="_blank" rel="noopener">`) / Notes / Actions (modifier `path(routeentry_update, {vaultId: vault.id, id: entry.id})`, supprimer, si `is_granted('vault_edit', vault)`).
  - ligne vide : `<td colspan="6" class="text-center text-muted">Aucune entrée</td>`.

`templates/vault/entry/edit.html.twig` — copie de `templates/calendar/event/edit.html.twig` : barre Valider / Annuler → `path(isAdmin ? 'app_admin_vault_view' : 'app_user_vault_view', {slug: vault.slug})`, carte avec `form.title`, `form.login`, `form.password`, `form.url`, `form.notes`. En `update`, afficher sous le champ mot de passe la mention du `help` (déjà portée par le type).

### 10. Widget

`src/Controller/Widgets/VaultWidgetController.php` :

```php
#[Route('/user/widget/vault/{pageWidgetId}', name: 'app_user_pagewidget_vault')]
#[Route('/admin/widget/vault/{pageWidgetId}', name: 'app_admin_pagewidget_vault')]
public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
```

Corps (= `CalendarWidgetController` avec les écarts indiqués, tous obligatoires) :
1. `$pageWidget = $pageWidgetRepository->find($pageWidgetId); if (!$pageWidget) { return new Response(''); }`
2. `$user = $this->getUser();` — si `!$user instanceof User`, retourner `new Response('')` (l'anonyme n'a jamais accès ; le sous-requête ne repasse pas par le firewall, `vendor/symfony/security-http/Firewall.php:53`).
3. `$canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);`
4. config fusionnée : `$configFields = $pageWidget->getWidget()->getConfig() ?? []; $config = array_merge($this->widgetConfigService->getDefaults($configFields), $pageWidget->getContent() ?? []);`
5. sélection :
   - `mode === 'linked'` : `$vaults = $vaultRepository->findVaultsByGroups($pageWidget->getPage()->getGroups()->toArray());` **puis** `$vaults = array_filter($vaults, fn (Vault $v) => $vaultRepository->isVaultAccessibleForUser($v, $user));` — ne PAS copier `CalendarWidgetController:44-52` qui n'applique aucun filtre d'accès.
   - `mode === 'selected'` (défaut) : `$vaultId = $config['vaultId'] ?? null;` → `$vault = is_numeric($vaultId) ? $vaultRepository->find((int) $vaultId) : null;` ; si `null === $vault` ou `!isVaultAccessibleForUser($vault, $user)` → `return new Response('');` ; sinon `$vaults = [$vault]`.
   - aucun coffre exploitable → `new Response('')`.
6. `$entries = []; foreach ($vaults as $v) { foreach ($vaultEntryRepository->findByVault($v, (int) ($config['nbEntries'] ?? 10)) as $e) { $entries[] = $e; } }` puis `usort($entries, fn ($a, $b) => $a->getTitle() <=> $b->getTitle());` — n'envoyer que `$entries` au template, jamais `passwordCipher`.
7. `return $this->render('widget/vault.html.twig', ['pageWidget' => $pageWidget, 'canManage' => $canManage, 'entries' => $entries, 'isAdmin' => $isAdmin, 'routeReveal' => $isAdmin ? 'app_admin_vaultentry_reveal' : 'app_user_vaultentry_reveal', 'routeList' => $isAdmin ? 'app_admin_vault_list' : 'app_user_vault_list']);`

`templates/widget/vault.html.twig` :

```twig
{% if canManage %}
<div class="widget-actions">
    <a href="{{ path(routeList) }}" class="btn btn-sm btn-outline-secondary" title="Gérer mes coffres-forts"><i class="fas fa-eye"></i></a>
</div>
{% endif %}

{% if entries|length > 0 %}
<div class="d-flex flex-column gap-2">
    {% for entry in entries %}
        <div class="card vault-entry-row" data-entry-id="{{ entry.id }}">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">{{ entry.title }}</h6>
                        <div class="small text-muted mb-1">
                            {% if entry.login %}<span><i class="fas fa-user"></i> {{ entry.login }}</span>{% endif %}
                            {% if entry.url %}{% if entry.login %} &middot; {% endif %}<a href="{{ entry.url }}" target="_blank" rel="noopener">{{ entry.url }}</a>{% endif %}
                        </div>
                        <span class="vault-password" data-hidden="1">••••••</span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary vault-reveal-btn" data-entry-id="{{ entry.id }}" data-reveal-url="{{ path(routeReveal, {id: entry.id}) }}" title="Afficher"><i class="fas fa-eye"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary vault-copy-btn" data-entry-id="{{ entry.id }}" data-reveal-url="{{ path(routeReveal, {id: entry.id}) }}" title="Copier"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
        </div>
    {% endfor %}
</div>
{% endif %}
```

`src/DataFixtures/WidgetFixtures.php` : ajouter au tableau `$data` (à la suite de l'entrée `pagewidget_calendar`, avant la fermeture du tableau) :

```php
[
    'title' => 'Coffre-fort',
    'description' => 'Vos identifiants et mots de passe partagés.',
    'route' => 'pagewidget_vault',
    'icon' => 'locked',
    'titleBgColor' => null,
    'titleFontColor' => null,
    'bodyBgColor' => null,
    'bodyFontColor' => null,
    'withBorder' => true,
    'withTitle' => true,
    'height' => null,
    'hideIfEmpty' => true,
    'config' => [
        'mode' => ['type' => 'choice', 'label' => 'Mode', 'choices' => ['Coffre sélectionné' => 'selected', 'Coffres des groupes de la page' => 'linked'], 'default' => 'selected'],
        'vaultId' => ['type' => 'entity', 'class' => 'App\\Entity\\Vault', 'label' => 'Coffre-fort', 'placeholder' => 'Sélectionnez un coffre-fort'],
        'nbEntries' => ['type' => 'integer', 'label' => 'Nombre d\'entrées', 'default' => 10],
    ],
],
```

Ne PAS ajouter le cas spécial `mode => 'linked'` par défaut de `PageWidgetController::update` (réservé à `pagewidget_blog`).

### 11. Navigation

- `templates/base.html.twig`, sidebar admin (bloc `app_admin`, après l'entrée Calendriers) :
  ```twig
  <a href="{{ path('app_admin_vault_list') }}" title="Coffres-forts">
      <i class="fas fa-key fa-fw"></i>
      <span>Coffres-forts</span>
  </a>
  ```
- Même bloc dans la sidebar user (`app_user`, après « Mes Calendriers ») avec `app_user_vault_list` et le libellé « Mes Coffres-forts ».
- `templates/home/admin.html.twig` : carte `fas fa-key` « Coffres-forts » → `app_admin_vault_list`.
- `templates/home/user.html.twig` : carte `fas fa-key` « Mes Coffres-forts » → `app_user_vault_list`.
- Ne pas ajouter de lien dans la navbar publique (`templates/base.html.twig:175`), ni de clé `has_vaults` dans `PageParameterBag` : les coffres ne doivent pas être exposés hors zone authentifiée.

### 12. JavaScript révéler/copier

Dans `public/lib/app/app.js`, ajouter une section `//== VAULT ================================================================` (avant `//== LINK WIDGET`) :

- `.vault-reveal-btn` click : si `.vault-password` de la même `.vault-entry-row` a `data-hidden="1"` → `fetch($btn.data('reveal-url'), {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}})` ; si `!response.ok` → rien (laisser masqué) ; sinon `span.text(json.password || '—').attr('data-hidden', '0')` et icône `fa-eye-slash`. Sinon → remettre `••••••`, `data-hidden="1"`, icône `fa-eye`.
- `.vault-copy-btn` click : même `fetch` ; sur succès `navigator.clipboard.writeText(json.password)` et basculer l'icône en `fa-check` pendant 1,5 s ; en cas d'échec de `navigator.clipboard` (ou `json.password` vide), afficher le mot de passe dans la ligne (`span.text(...)`, `data-hidden="0"`) pour permettre une copie manuelle.
- Aucune donnée n'est chargée au rendu : pas de mot de passe dans le HTML initial.

Dans `templates/base.html.twig:93`, incrémenter le cache-buster : `{{ asset('lib/app/app.js') }}?v=20261031`.

### 13. Test unitaire du chiffrement

`tests/Service/VaultCipherTest.php` (`PHPUnit\Framework\TestCase`, pas de kernel, pas de base) — `tests/` ne contient aujourd'hui que `bootstrap.php`, `App\Tests\` est déjà mappé (`composer.json` autoload-dev) :

```php
private function cipher(?string $key = null): VaultCipher
{
    return new VaultCipher(new ParameterBag(['appEncryptKey' => base64_encode($key ?? str_repeat('a', 32))]));
}
```

Trois cas : (1) `decrypt(encrypt('s3cr3t')) === 's3cr3t'` ; (2) deux `encrypt` du même clair produisent des chaînes différentes et commencent par `v1:` ; (3) `cipher(str_repeat('b', 32))->decrypt($cipher()->encrypt('x'))` lève `\RuntimeException`.

## Critical files & anchors

- `src/Controller/Widgets/CalendarWidgetController.php:25-66` — squelette du widget ; ses lignes 44-56 n'appliquent **aucun** filtre d'accès sur le mode `linked` : ne pas recopier.
- `src/Form/CalendarType.php:45-121` — bloc de partage admin (`user`/`groups`/`allUsers`/`roles`) et bloc non-admin restreint aux groupes MASTER ; c'est la référence champ par champ.
- `templates/calendar/edit.html.twig:44-146` — wrappers de permissions + script de bascule à adapter (avec désactivation des champs masqués).
- `src/Repository/CalendarRepository.php:29-59` — `isCalendarAccessibleForUser` ; notre version en diffère sur deux points (anonyme refusé ; bypass admin hors repository).
- `src/Entity/Page.php:127-131` — `clearGroups()`, à copier dans `Vault`.

## Verification

Prérequis : conteneurs `ninegate` et `ninegate-postgres` démarrés (`docker compose up -d`), URL `http://localhost:8019`, comptes fixture `admin` / `master` / `user` (mot de passe `APP_ADMIN_PASSWORD` de `.env`).

1. **Schéma** — `docker exec ninegate php bin/console doctrine:schema:update --force --complete` puis
   `docker exec ninegate-postgres psql -U user -d ninegate -c "\d vault" -c "\d vault_entry" -c "\d vault_group"`
   → tables `vault`, `vault_entry` présentes, `vault_group` avec `vault_id`/`group_id`, `vault_entry.password_cipher` de type `text`.
2. **Fixtures** — `docker exec ninegate php bin/console app:init` puis
   `docker exec ninegate-postgres psql -U user -d ninegate -c "select id,title,route,config from widget where route='pagewidget_vault';"`
   → une ligne avec `config` contenant `vaultId`/`mode`/`nbEntries` et `icon_id` non nul.
3. **Chiffrement (unitaire)** — `docker exec ninegate vendor/bin/phpunit tests/Service/VaultCipherTest.php` → 3 tests verts.
4. **CRUD + partage** — en `admin` :
   - créer un coffre « Perso test » type personnel → il apparaît dans `/user/vault` sous l'admin uniquement ;
   - créer un coffre « Équipe test » avec le groupe « Tous » → visible par `master` et `user` dans `/user/vault`, absente pour un visiteur déconnecté (`/user/vault` redirige vers `/login`) ;
   - ouvrir `/user/vault/view/equipe-test`, ajouter une entrée (titre `Compte test`, login `demo`, mot de passe `P@ssw0rd-42`), vérifier en base que `password_cipher` **ne contient pas** `P@ssw0rd-42` :
     `docker exec ninegate-postgres psql -U user -d ninegate -c "select title,password_cipher from vault_entry;"`
   - rouvrir l'entrée, laisser le champ mot de passe vide, changer le titre → le bouton révéler affiche toujours `P@ssw0rd-42` (preuve que le chiffré a été conservé).
5. **Révélation** — se connecter en `user` (non propriétaire, membre du groupe), ouvrir `/user/vault/view/equipe-test` : le HTML initial ne contient aucune occurrence de `P@ssw0rd-42` (`docker exec ninegate sh -c "curl -s -c /tmp/c -b /tmp/c ..."` ou inspection navigateur du source) ; clic sur l'œil → le mot de passe apparaît ; clic sur copier → presse-papier contient `P@ssw0rd-42`.
6. **Refus d'accès** — en `user`, ouvrir `/admin/vault` → 403 ; ouvrir `/user/vault/view/perso-test` (coffre personnel de l'admin) → 403 ; `curl -X POST /user/vault/entry/reveal/<id de l'entrée du coffre admin>` avec la session `user` → HTTP 403 et corps `{"error":"Accès refusé"}`.
7. **Widget** — sur `/admin/page` → widgets de la page, ajouter le widget « Coffre-fort », en configuration choisir `mode = Coffre sélectionné`, coffre `Équipe test`, `nbEntries = 10`, enregistrer ; consulter la page en `user` → la carte affiche « Compte test », mot de passe masqué, œil/copier fonctionnels ; choisir un coffre auquel l'utilisateur n'a pas accès (coffre perso de l'admin) → la carte est vide (le widget disparaît si `hideIfEmpty`) ; passer `mode = linked` sur une page dont les groupes ne donnent accès à aucun coffre → carte vide, aucun `password_cipher` ni titre de coffre inaccessible dans la réponse HTML.
8. **Non-régression** — `/user/calendar`, `/user/blog`, `/admin/calendar` répondent 200 ; `docker exec ninegate php bin/console cache:clear`.

## Assumptions & contingencies

- Le test fonctionnel repose sur les conteneurs et la base de dev (`DATABASE_URL` de `.env`) : `phpunit.dist.xml` force `APP_ENV=test` + suffixe `_test`, or la base `ninegate_test` n'existe pas (`psql -l` ne liste que `ninegate`). Ne lancer que le test unitaire de l'étape 13 ; si un test kernel est ajouté, créer d'abord la base (`createdb ninegate_test`) — sinon s'en tenir aux vérifications HTTP manuelles ci-dessus.
- `APP_ENCRYPT_KEY` du dépôt est une valeur d'exemple (`changeme`-like). Si l'instance cible a `APP_ENCRYPT_KEY` absent ou non base64/32 octets, `VaultCipher` lève au premier usage : générer la clé (`echo base64_encode(random_bytes(32))`) dans `.env` **avant** la première entrée. Changer la clé après coup rend indéchiffrables les entrées existantes : aucun mécanisme de rotation/re-chiffrement n'est prévu dans cette version.
- Les sous-requêtes de widget ne repassent pas par le firewall (`Firewall::onKernelRequest` sort si `!isMainRequest`, `vendor/symfony/security-http/Firewall.php:51-55`) : la garde `!$user instanceof User → Response('')` du contrôleur de widget est donc la seule protection pour l'anonyme. Ne pas la retirer.
- `doctrine:schema:update` est la méthode de schéma du projet : ne pas créer de migration Doctrine.
