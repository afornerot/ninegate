<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class IdentityProvider
{
    public const MODE_SQL  = 'SQL';
    public const MODE_CAS  = 'CAS';
    public const MODE_OIDC = 'OIDC';

    public const MASTER_SQL  = 'SQL';
    public const MASTER_LDAP = 'LDAP';
    public const MASTER_SSO  = 'SSO';

    public const SYNC_DISABLED   = 'false';
    public const SYNC_NINE2LDAP   = 'NINE2LDAP';
    public const SYNC_LDAP2NINE   = 'LDAP2NINE';

    private string $modeAuth;
    private string $masterIdentity;
    private string $syncIdentity;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->modeAuth = (string) $parameterBag->get('appModeAuth');
        $this->masterIdentity = (string) $parameterBag->get('appMasterIdentity');
        $this->syncIdentity = (string) ($parameterBag->get('syncIdentity') ?? 'false');

        $this->validate();
    }

    private function validate(): void
    {
        $allowedMaster = [self::MASTER_SQL, self::MASTER_LDAP, self::MASTER_SSO];
        if (!in_array($this->masterIdentity, $allowedMaster, true)) {
            throw new \InvalidArgumentException(sprintf(
                'APP_MASTERIDENTITY=%s invalide. Valeurs autorisées : %s',
                $this->masterIdentity,
                implode(', ', $allowedMaster)
            ));
        }

        if (self::MASTER_SQL !== $this->masterIdentity
            && !in_array($this->modeAuth, [self::MODE_CAS, self::MODE_OIDC], true)) {
            throw new \InvalidArgumentException(sprintf(
                'APP_MASTERIDENTITY=%s requiert APP_MODEAUTH=CAS ou OIDC (actuellement %s)',
                $this->masterIdentity,
                $this->modeAuth
            ));
        }

        $allowedSync = [
            self::SYNC_DISABLED,
            self::SYNC_NINE2LDAP,
            self::SYNC_LDAP2NINE,
        ];
        if (!in_array($this->syncIdentity, $allowedSync, true)) {
            throw new \InvalidArgumentException(sprintf(
                'SYNC_IDENTITY=%s invalide. Valeurs autorisées : %s',
                $this->syncIdentity,
                implode(', ', $allowedSync)
            ));
        }
    }

    public function getModeAuth(): string
    {
        return $this->modeAuth;
    }

    public function getMasterIdentity(): string
    {
        return $this->masterIdentity;
    }

    public function getSyncIdentity(): string
    {
        return $this->syncIdentity;
    }

    public function isSyncEnabled(): bool
    {
        return self::SYNC_DISABLED !== $this->syncIdentity;
    }

    public function isSyncNineToLdap(): bool
    {
        return self::SYNC_NINE2LDAP === $this->syncIdentity;
    }

    public function isSyncLdapToNine(): bool
    {
        return self::SYNC_LDAP2NINE === $this->syncIdentity;
    }

    public function canSyncToLdap(): bool
    {
        return self::MASTER_SQL !== $this->masterIdentity;
    }

    public function canReceiveFromLdap(): bool
    {
        return self::MASTER_SQL !== $this->masterIdentity;
    }

    public function isIdentityLocal(): bool
    {
        return self::MASTER_SQL === $this->masterIdentity;
    }

    public function isIdentityExternal(): bool
    {
        return !$this->isIdentityLocal();
    }

    public function isMasterSQL(): bool
    {
        return self::MASTER_SQL === $this->masterIdentity;
    }

    public function isMasterLDAP(): bool
    {
        return self::MASTER_LDAP === $this->masterIdentity;
    }

    public function isMasterSSO(): bool
    {
        return self::MASTER_SSO === $this->masterIdentity;
    }

    public function canManagePassword(): bool
    {
        return self::MASTER_SQL === $this->masterIdentity;
    }

    public function canCreateUser(): bool
    {
        return self::MASTER_SQL === $this->masterIdentity;
    }
}