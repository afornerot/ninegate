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

        if ($mapping && !$this->ldapService->dnEquals($mapping->getLdapDn(), $newDn)) {
            // MODRDN en cas de rename du username
            $this->ldapService->modifyRdn($mapping->getLdapDn(), 'uid=' . $this->normalizeForUid($user->getUsername()));
            $mapping->setLdapDn($newDn);
        }

        if (null === $mapping) {
            $this->ensureUserMapping($user, $newDn);
            return;
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
     * Supprime l'entrée LDAP + le mapping d'un user supprimé en BDD.
     * Nettoie aussi les memberuid des groupes qui le référencent encore.
     */
    public function removeUser(int $userId): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        $mapping = $this->mappingRepository->findForUser($userId);
        if (!$mapping) {
            return;
        }

        $this->ldapService->delete($mapping->getLdapDn());
        $this->em->remove($mapping);
        $this->em->flush();

        // memberuid résiduels dans les groupes : nettoyage
        $this->cleanupMemberUid($this->normalizeForUid($mapping->getLdapUid() ?? ''));
    }

    /**
     * Supprime l'entrée LDAP + le mapping d'un group supprimé en BDD.
     */
    public function removeGroup(int $groupId): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        $mapping = $this->mappingRepository->findForGroup($groupId);
        if (!$mapping) {
            return;
        }

        $this->ldapService->delete($mapping->getLdapDn());
        $this->em->remove($mapping);
        $this->em->flush();
    }

    /**
     * Retire $uid des memberuid de toutes les groupes qui le référencent.
     */
    private function cleanupMemberUid(string $uid): void
    {
        if ('' === $uid) {
            return;
        }

        $groupsDn = $this->parameterBag->get('ldapGroupsDn');
        $results = $this->ldapService->search($groupsDn, '(memberuid='.$uid.')', ['dn']);
        foreach ($results as $entry) {
            $dn = method_exists($entry, 'getDn') ? $entry->getDn() : ($entry['dn'] ?? null);
            if (null === $dn) {
                continue;
            }
            $this->ldapService->modify((string) $dn, ['memberuid' => []]);
        }
    }

    /**
     * Crée l'entrée LDAP + le mapping d'un user sans mapping (cf. batch).
     * Idempotence : DN déjà présent -> mapping seul, la sync le traitera
     * en update au prochain tour.
     */
    private function ensureUserMapping(User $user, string $newDn): void
    {
        $alreadyThere = $this->ldapService->exists($newDn);
        if (!$alreadyThere) {
            if (!$this->ldapService->add($newDn, [
                'objectclass'   => ['top', 'inetOrgPerson', 'posixAccount'],
                'uid'           => $this->normalizeForUid($user->getUsername()),
                'cn'            => trim(($user->getFirstname() ?? '') . ' ' . ($user->getLastname() ?? '')) ?: $this->normalizeForUid($user->getUsername()),
                'uidnumber'     => (string) ($user->getId() + 1000),
                'gidnumber'     => '1000',
                'mail'          => $user->getEmail() ?? '',
                'sn'            => $user->getLastname() ?? $user->getUsername(),
                'homedirectory' => '/home/' . $this->normalizeForUid($user->getUsername()),
                'loginshell'    => '/bin/bash',
            ] + (null !== $user->getOpenLdapPassword() && '' !== $user->getOpenLdapPassword()
                ? ['userpassword' => $user->getOpenLdapPassword()]
                : []))) {
                return;
            }
        }

        $mapping = new LdapMapping();
        $mapping->setEntityType(LdapMapping::TYPE_USER);
        $mapping->setEntityId($user->getId());
        $mapping->setLdapDn($newDn);
        $mapping->setLdapUid($user->getUsername());
        $mapping->setSyncedAt(new \DateTimeImmutable());
        $this->em->persist($mapping);
        $this->em->flush();
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

        if ($mapping && !$this->ldapService->dnEquals($mapping->getLdapDn(), $newDn)) {
            $this->ldapService->modifyRdn($mapping->getLdapDn(), 'cn=' . $this->normalizeForCn($group->getSlug()));
            $mapping->setLdapDn($newDn);
        }

        $members = $this->resolveMemberUids($group);
        $memberAttr = (string) $this->parameterBag->get('ldapAttrGroupMemberDn');
        $memberDns = '' !== $memberAttr ? $this->resolveMemberDns($group) : [];

        if (null === $mapping) {
            // Nouveau groupe : création de l'entrée LDAP + mapping (cf. batch).
            // Idempotence : le DN peut déjà exister (run interrompu ou créé
            // hors sync) -> on enregistre juste le mapping, la sync le
            // traitera en update au prochain tour.
            $gidNumber = $group->getId() + 1000;
            $alreadyThere = $this->ldapService->exists($newDn);
            if (!$alreadyThere) {
                // Schéma étendu (ex: cadolesGroup/cadolesmember) uniquement si
                // LDAP_ATTR_GROUP_MEMBER_DN est configuré ; sinon posixGroup seul.
                $attrs = [
                    'cn'          => $this->normalizeForCn($group->getSlug()),
                    'gidnumber'   => (string) $gidNumber,
                    'description' => $group->getDescription() ?? '',
                    'memberuid'   => $members,
                ];
                if ('' !== $memberAttr) {
                    // Toujours cadolesGroup si le schéma étendu est configuré :
                    // un groupe vide doit accepter un cadolesmember posé par une
                    // appli externe (l'attribut lui-même n'est ajouté qu'avec valeurs).
                    $attrs['objectclass'] = ['top', 'posixGroup', 'cadolesGroup'];
                } else {
                    $attrs['objectclass'] = ['top', 'posixGroup'];
                }
                if (!$this->ldapService->add($newDn, $attrs)) {
                    return;
                }
            }

            $mapping = new LdapMapping();
            $mapping->setEntityType(LdapMapping::TYPE_GROUP);
            $mapping->setEntityId($group->getId());
            $mapping->setLdapDn($newDn);
            $mapping->setLdapGidNumber($alreadyThere ? null : $gidNumber);
            $mapping->setSyncedAt(new \DateTimeImmutable());
            $this->em->persist($mapping);
            $this->em->flush();
            // Entrée créée : memberuid déjà posé par add(), rien d'autre à faire.
            return;
        }

        $this->ldapService->modify($newDn, [] !== $memberDns
            // objectclass inclus : les entrées préexistantes (créées avant la
            // refonte du schéma ou importées incomplètes) doivent passer en
            // cadolesGroup pour accepter l'attribut DN.
            ? [
                'objectclass'   => ['top', 'posixGroup', 'cadolesGroup'],
                'description'   => $group->getDescription() ?? '',
                'memberuid'     => $members,
                $memberAttr     => $memberDns,
            ]
            : [
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
        $memberAttr = (string) $this->parameterBag->get('ldapAttrGroupMemberDn');
        $memberDns = '' !== $memberAttr ? $this->resolveMemberDns($group) : [];

        // modify() no-op sur valeurs vides : la suppression du dernier
        // membre passe par une vraie suppression d'attribut (ldap_mod_del).
        $mods = ['memberuid' => $members];
        if ('' !== $memberAttr) {
            $mods[$memberAttr] = $memberDns;
        }
        $this->ldapService->modify($mapping->getLdapDn(), $mods);

        $toRemove = [];
        if ([] === $members) {
            $toRemove[] = 'memberuid';
        }
        if ('' !== $memberAttr && [] === $memberDns) {
            $toRemove[] = $memberAttr;
        }
        if ([] !== $toRemove) {
            $this->ldapService->removeAttributes($mapping->getLdapDn(), $toRemove);
        }
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

    /**
     * DN complets des membres (attribut cadolesMember), même format que
     * l'annuaire d'origine : uid=x,<usersDN>.
     */
    private function resolveMemberDns(AppGroup $group): array
    {
        $usersDn = $this->parameterBag->get('ldapUsersDn');
        $dns = [];
        foreach ($group->getUserGroups() as $userGroup) {
            $user = $userGroup->getUser();
            if ($user && null !== $user->getUsername()) {
                $dns[] = 'uid='.$this->normalizeForUid($user->getUsername()).','.$usersDn;
            }
        }
        $dns = array_values(array_unique($dns));
        sort($dns);
        return $dns;
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