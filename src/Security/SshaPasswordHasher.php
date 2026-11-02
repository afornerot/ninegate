<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Hasher de transition entre ninegate v2 (hashs LDAP {SSHA}) et v3 (bcrypt).
 *
 * - `verify()` accepte les deux formats : {SSHA} herite de v2 et bcrypt ecrit
 *   par v3 ;
 * - `hash()` produit toujours du bcrypt : les mots de passe crees apres la
 *   migration ne sont jamais en SSHA ;
 * - `needsRehash()` signale les hashs {SSHA} pour que Symfony les remplace
 *   par du bcrypt a la premiere connexion reussie.
 *
 * Format {SSHA} : base64(sha1(password . salt) . salt), soit 20 octets
 * d'empreinte suivis du sel.
 */
class SshaPasswordHasher implements PasswordHasherInterface
{
    private const PREFIX = '{SSHA}';

    private NativePasswordHasher $fallback;

    public function __construct(?int $cost = null)
    {
        $this->fallback = new NativePasswordHasher(null, null, $cost);
    }

    public function hash(#[\SensitiveParameter] string $plainPassword, ?string $salt = null): string
    {
        return $this->fallback->hash($plainPassword, $salt);
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, ?string $salt = null): bool
    {
        if (str_starts_with($hashedPassword, self::PREFIX)) {
            return $this->verifySsha($hashedPassword, $plainPassword);
        }

        // Tout ce qui n'est pas SSHA (bcrypt, argon2...) est delegue.
        return $this->fallback->verify($hashedPassword, $plainPassword, $salt);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        if (str_starts_with($hashedPassword, self::PREFIX)) {
            return true;
        }

        return $this->fallback->needsRehash($hashedPassword);
    }

    private function verifySsha(string $hashedPassword, string $plainPassword): bool
    {
        $raw = base64_decode(substr($hashedPassword, \strlen(self::PREFIX)), true);

        // 20 octets d'empreinte + au moins 1 octet de sel
        if ($raw === false || \strlen($raw) <= 20) {
            return false;
        }

        $digest = substr($raw, 0, 20);
        $salt = substr($raw, 20);

        return hash_equals($digest, sha1($plainPassword.$salt, true));
    }
}
