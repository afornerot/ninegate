<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * Wrapper autour de Symfony LDAP pour les opérations courantes
 * sur l'annuaire OpenLDAP.
 */
class LdapService
{
    private bool $bound = false;

    public function __construct(
        private AdapterInterface $adapter,
        private ParameterBagInterface $parameterBag,
        private LdapNormalizer $normalizer,
    ) {
    }

    private function ensureBound(): void
    {
        if (!$this->bound) {
            $this->bind();
        }
    }

    public function bind(): void
    {
        $host = $this->parameterBag->get('ldapHost');
        $port = $this->parameterBag->get('ldapPort');
        $writerDn = $this->parameterBag->get('ldapWriterDn');
        $writerPwd = $this->parameterBag->get('ldapWriterPassword');

        $this->adapter->getConnection()->bind($writerDn, $writerPwd);
        $this->bound = true;
    }

    public function search(string $baseDn, string $filter, array $attrs = []): array
    {
        $this->ensureBound();
        try {
            $query = $this->adapter->createQuery($baseDn, $filter, ['filter' => $attrs]);
            return $query->execute()->toArray();
        } catch (LdapException $e) {
            return [];
        }
    }

    public function getEntry(string $dn): ?Entry
    {
        $this->ensureBound();
        try {
            // ExtLdap n'expose pas de get() : lecture par query base-objet
            // options: scope 'base' = lecture de l'entrée elle-même
            $results = $this->adapter->createQuery($dn, 'objectClass=*', ['scope' => 'base'])->execute();

            return isset($results[0]) ? $results[0] : null;
        } catch (LdapException $e) {
            return null;
        }
    }

    /**
     * Comparaison de DN au sens LDAP (RFC 4514) : insensible à la casse et
     * aux espaces autour des séparateurs. Permet d'éviter des MODRDN inutiles
     * quand le DN historique diffère uniquement par la casse (cn=AO vs cn=ao).
     */
    public function dnEquals(string $a, string $b): bool
    {
        $normalize = static function (string $dn): string {
            // Découpe sur les virgules non échappées, normalise chaque RDN
            $rdns = preg_split('/(?<!\\\\),/', $dn) ?: [];
            $normalized = array_map(static function (string $rdn): string {
                [$attr, $value] = array_pad(explode('=', $rdn, 2), 2, '');
                return strtolower(trim($attr)).'='.strtolower(trim($value));
            }, $rdns);

            return implode(',', $normalized);
        };

        return $normalize($a) === $normalize($b);
    }

    public function exists(string $dn): bool
    {
        return null !== $this->getEntry($dn);
    }

    public function add(string $dn, array $attrs): bool
    {
        $this->ensureBound();
        try {
            $entry = new Entry($dn, $this->normalizeAttrs($attrs));
            $this->adapter->getEntryManager()->add($entry);
            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException('LDAP add failed for "' . $dn . '": ' . $e->getMessage(), 0, $e);
        }
    }

    public function modify(string $dn, array $mods): bool
    {
        $this->ensureBound();
        // Rien à écrire après normalisation (valeurs vides retirées) : no-op,
        // sinon ldap_modify() rejette une entry vide.
        if ([] === $this->normalizeAttrs($mods)) {
            return true;
        }
        try {
            $this->adapter->getEntryManager()->update(new Entry($dn, $this->normalizeAttrs($mods)));
            return true;
        } catch (LdapException $e) {
            return false;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Supprime des attributs d'une entrée (ldap_mod_del). Nécessaire quand la
     * dernière valeur disparaît : modify() no-op sur les valeurs vides.
     */
    public function removeAttributes(string $dn, array $attrs): bool
    {
        $this->ensureBound();
        // Un attribut par mod_del : "no such attribute" (errno 16) sur l'un
        // ne doit pas empêcher la suppression des autres (mod atomique).
        $ok = true;
        foreach ($attrs as $attr) {
            $errno = 0;
            set_error_handler(static function (int $n, string $s) use (&$errno): bool {
                if (preg_match('/\((\d+)\)/', $s, $m)) {
                    $errno = (int) $m[1];
                }
                return true;
            }, E_WARNING);
            try {
                $done = ldap_mod_del($this->adapter->getConnection()->getResource(), $dn, [$attr => []]);
            } catch (\Throwable $e) {
                $done = false;
            } finally {
                restore_error_handler();
            }
            // 16 = no such attribute : l'attribut est déjà absent, objectif atteint.
            if (!$done && 16 !== $errno) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function modifyRdn(string $oldDn, string $newRdn): bool
    {
        $this->ensureBound();
        $parsed = $this->parseDn($oldDn);
        if (null === $parsed) {
            return false;
        }
        [$rdn, $parent] = $parsed;
        $newDn = $newRdn . ($parent ? ',' . $parent : '');
        try {
            $this->adapter->getEntryManager()->rename(new Entry($oldDn, []), $newRdn, $parent);
            return true;
        } catch (LdapException $e) {
            return false;
        }
    }

    public function delete(string $dn): bool
    {
        $this->ensureBound();
        try {
            $this->adapter->getEntryManager()->remove(new Entry($dn));
            return true;
        } catch (LdapException $e) {
            return false;
        }
    }

    public function userDnFor(string $uid): string
    {
        $usersDn = $this->parameterBag->get('ldapUsersDn');
        $uidClean = $this->normalizer->escapeDn($this->normalizer->normalizeForUid($uid));
        return sprintf('uid=%s,%s', $uidClean, $usersDn);
    }

    public function groupDnFor(string $cn): string
    {
        $groupsDn = $this->parameterBag->get('ldapGroupsDn');
        $cnClean = $this->normalizer->escapeDn($this->normalizer->normalizeForCn($cn));
        return sprintf('cn=%s,%s', $cnClean, $groupsDn);
    }

    /**
     * Convertit toutes les valeurs d'attributs en arrays (Symfony LDAP attend array<string, array>).
     * Les chaînes vides et arrays vides sont retirés (OpenLDAP rejette avec Invalid syntax).
     */
    private function normalizeAttrs(array $attrs): array
    {
        $normalized = [];
        foreach ($attrs as $key => $value) {
            if (is_string($value)) {
                if ($value === '') {
                    continue;
                }
                $normalized[$key] = [$value];
                continue;
            }
            if (is_array($value)) {
                $clean = array_filter($value, static fn ($v) => is_string($v) ? $v !== '' : $v !== null);
                if (empty($clean)) {
                    continue;
                }
                $normalized[$key] = array_values($clean);
                continue;
            }
            $normalized[$key] = [$value];
        }
        return $normalized;
    }

    private function parseDn(string $dn): ?array
    {
        $parts = preg_split('/,(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/', $dn, 2);
        if (count($parts) !== 2) {
            return [$dn, ''];
        }
        return [$parts[0], $parts[1]];
    }
}