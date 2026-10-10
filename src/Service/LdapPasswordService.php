<?php

namespace App\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Service centralisant le calcul du mot de passe hashé au format LDAP.
 *
 * Le format canonique stocké dans User::ldapPassword est `{BCRYPT}$2y$12$...`
 * (hash bcrypt natif de Symfony, lisible et standard). Ce format est utilisé pour
 * pouvoir re-pousser le hash en cas de mise à jour.
 *
 * Pour la destination OpenLDAP (Alpine), l'image officielle ne supporte pas
 * bcrypt via `crypt(3)` (musl sans bcrypt). On stocke donc un second hash
 * `User::openLdapPassword` au format `{CRYPT}$6$...` (SHA-512 crypt natif)
 * généré via `crypt()` de PHP. Ce format est natif sur musl/glibc Alpine
 * sans nécessiter de module supplémentaire côté slapd.
 *
 * Aucun flux ne repasse en clair : les hashes sont produits au moment d'un
 * changement de password (UserController/ResetPassword/UserPasswordCommand)
 * et stockés tels quels. Le service n'a accès qu'à l'algo de hash, jamais
 * au mot de passe en clair (sauf lors de l'init, capturé en clair le temps
 * de la requête HTTP).
 */
class LdapPasswordService
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    /**
     * Hash un mot de passe en clair au format canonique `{BCRYPT}$2y$...`
     * pour stockage dans User::ldapPassword.
     */
    public function hashForLdap(string $plainPassword): string
    {
        $hasher = $this->hasherFactory->getPasswordHasher('ldap');
        $hash = $hasher->hash($plainPassword);

        $hash = preg_replace('/^\$2[a|b]\$/', '$2y$', $hash);

        return '{BCRYPT}' . $hash;
    }

    /**
     * Hash compatible avec OpenLDAP Alpine via `crypt(3)` (SHA-512).
     *
     * Format produit : `{CRYPT}$6$<salt>$<hash>`.
     * Ce format est natif sur la glibc et musl Alpine, sans nécessiter
     * de module supplémentaire côté slapd.
     */
    public function hashForOpenLdap(string $plainPassword): string
    {
        $salt = substr(bin2hex(random_bytes(8)), 0, 16);
        $hash = crypt($plainPassword, '$6$' . $salt . '$');

        return '{CRYPT}' . $hash;
    }

    /**
     * Vérifie qu'un mot de passe en clair correspond à un hash LDAP stocké.
     */
    public function verify(string $plainPassword, string $ldapHash): bool
    {
        $hash = $ldapHash;
        if (str_starts_with($hash, '{BCRYPT}')) {
            $hash = substr($hash, strlen('{BCRYPT}'));
            return password_verify($plainPassword, $hash);
        }
        if (str_starts_with($hash, '{CRYPT}')) {
            $hash = substr($hash, strlen('{CRYPT}'));
            return hash_equals($hash, crypt($plainPassword, $hash));
        }
        return false;
    }
}