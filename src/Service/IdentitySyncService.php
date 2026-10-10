<?php

namespace App\Service;

use App\Entity\Group as AppGroup;
use App\Entity\LdapMapping;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\GroupRepository;
use App\Repository\LdapMappingRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service bas-niveau qui pousse les entités ninegate vers OpenLDAP.
 * Utilisé à la fois :
 *   - en batch par la commande IdentitySyncNine2LdapCommand
 *   - en temps réel par IdentitySyncHandler (consommateur Messenger)
 *
 * Toutes les méthodes sont idempotentes et peuvent être appelées
 * indépendamment (sync incrémentale d'un seul user/group).
 */
class IdentitySyncService
{
    public function __construct(
        private IdentityProvider $identityProvider,
        private AppAdminGuard $adminGuard,
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
        private UserGroupRepository $userGroupRepository,
        private LdapMappingRepository $mappingRepository,
        private LdapService $ldapService,
        private EntityManagerInterface $em,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Pousse un user vers OpenLDAP (si MASTERIDENTITY != SQL et SYNC_NINE2LDAP).
     */
    public function syncUser(User $user): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        // APP_ADMIN n'est pas skip : on le pousse vers LDAP pour permettre
        // le bind admin. Il reste "intouchable" côté métier (rôle non déclassable).

        $mapping = $this->mappingRepository->findForUser($user->getId());
        $newDn = $this->ldapService->userDnFor($user->getUsername());

        if ($mapping && $mapping->getLdapDn() !== $newDn) {
            // MODRDN en cas de rename du username
            $this->ldapService->modifyRdn($mapping->getLdapDn(), 'uid=' . $this->normalizeForUid($user->getUsername()));
            $mapping->setLdapDn($newDn);
        }

        $attrs = [
            'mail'      => $user->getEmail() ?? '',
            'givenname' => $user->getFirstname() ?? '',
            'sn'        => $user->getLastname() ?? $user->getUsername(),
        ];

        $openLdapHash = $user->getOpenLdapPassword();
        if (null !== $openLdapHash && '' !== $openLdapHash) {
            $attrs['userpassword'] = $openLdapHash;
        }

        $this->ldapService->modify($newDn, $attrs);

        $mapping->setSyncedAt(new \DateTimeImmutable());
    }

    /**
     * Pousse un group vers OpenLDAP (si group non-annuaire).
     */
    public function syncGroup(AppGroup $group): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        if ($group->isAnnuaire()) {
            return;
        }

        $mapping = $this->mappingRepository->findForGroup($group->getId());
        $newDn = $this->ldapService->groupDnFor($group->getSlug());

        if ($mapping && $mapping->getLdapDn() !== $newDn) {
            $this->ldapService->modifyRdn($mapping->getLdapDn(), 'cn=' . $this->normalizeForCn($group->getSlug()));
            $mapping->setLdapDn($newDn);
        }

        $members = $this->resolveMemberUids($group);

        $this->ldapService->modify($newDn, [
            'description' => $group->getDescription() ?? '',
            'memberuid'   => $members,
        ]);

        $mapping->setSyncedAt(new \DateTimeImmutable());
    }

    /**
     * Synchronise les memberUID d'un group (résolution depuis UserGroup).
     * Appelé après syncGroup ou séparément.
     */
    public function syncGroupMembers(AppGroup $group): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        if ($group->isAnnuaire()) {
            return;
        }

        $mapping = $this->mappingRepository->findForGroup($group->getId());
        if (!$mapping) {
            // Pas encore mappé : le sync de groupe créera l'entrée
            $this->syncGroup($group);
            return;
        }

        $members = $this->resolveMemberUids($group);

        $this->ldapService->modify($mapping->getLdapDn(), [
            'memberuid' => $members,
        ]);
        $mapping->setSyncedAt(new \DateTimeImmutable());
    }

    /**
     * @return string[]
     */
    private function resolveMemberUids(AppGroup $group): array
    {
        $uids = [];
        foreach ($group->getUserGroups() as $userGroup) {
            $user = $userGroup->getUser();
            if ($user && null !== $user->getUsername()) {
                $uids[] = $this->normalizeForUid($user->getUsername());
            }
        }
        $uids = array_values(array_unique($uids));
        sort($uids);
        return $uids;
    }

    private function normalizeForUid(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9._-]/u', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        $value = trim($value, '_-.');
        return $value !== '' ? $value : 'user';
    }

    private function normalizeForCn(string $value): string
    {
        $value = preg_replace('/[,+"\\\\<>=;#]/u', '_', $value);
        return trim($value);
    }
}