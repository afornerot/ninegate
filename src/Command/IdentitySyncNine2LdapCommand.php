<?php

namespace App\Command;

use App\Entity\Group as AppGroup;
use App\Entity\LdapMapping;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\GroupRepository;
use App\Repository\LdapMappingRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Service\AppAdminGuard;
use App\Service\IdentityProvider;
use App\Service\LdapAdapterFactory;
use App\Service\LdapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * SENS : ninegate → OpenLDAP
 *
 * Pousse les users, groups et relations depuis la BDD ninegate vers
 * l'annuaire OpenLDAP. Utilise la table ldap_mapping pour détecter
 * les renames et faire des MODRDN (préservation des gidnumber).
 *
 * Règles métier :
 * - APP_ADMIN est intouchable (skip complet).
 * - Les groupes avec isAnnuaire=true sont gérés par LDAP2NINE — on skip.
 * - Aucun rôle applicatif (ROLE_ADMIN/ROLE_MASTER/ROLE_USER) n'est
 *   poussé vers LDAP. Le gidnumber LDAP est un groupe POSIX (technique).
 */
#[AsCommand(
    name: 'app:identity:nine2ldap',
    description: 'Pousse les données ninegate vers OpenLDAP',
)]
class IdentitySyncNine2LdapCommand extends Command
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', null, InputOption::VALUE_OPTIONAL, 'Sync ciblée sur un user')
            ->addOption('group-id', null, InputOption::VALUE_OPTIONAL, 'Sync ciblée sur un group')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture')
            ->addOption('no-purge', null, InputOption::VALUE_NONE, 'Ne pas purger les orphelins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            $output->writeln('<error>SYNC_IDENTITY ne concorde pas avec cette commande (attendu: NINE2LDAP).</error>');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $noPurge = (bool) $input->getOption('no-purge');

        $io->title('SYNC NINE → LDAP');
        if ($dryRun) {
            $io->note('Mode dry-run — aucune écriture.');
        }

        try {
            $this->ldapService->bind();
        } catch (\Throwable $e) {
            $io->error('Impossible de se connecter à OpenLDAP : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $userId = $input->getOption('user-id') !== null ? (int) $input->getOption('user-id') : null;
        $groupId = $input->getOption('group-id') !== null ? (int) $input->getOption('group-id') : null;

        $this->syncGroups($io, $dryRun, $groupId);
        $this->syncUsers($io, $dryRun, $userId);

        if (!$noPurge) {
            $this->purgeOrphans($io, $dryRun);
        } else {
            $io->note('--no-purge : purge des orphelins désactivée.');
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success('NINE2LDAP terminé.');
        return Command::SUCCESS;
    }

    private function syncGroups(SymfonyStyle $io, bool $dryRun, ?int $groupId): void
    {
        $criteria = [];
        if ($groupId !== null) {
            $criteria['id'] = $groupId;
        }

        $groups = $this->groupRepository->findBy($criteria);
        foreach ($groups as $group) {
            if ($group->isAnnuaire()) {
                $io->warning(sprintf('  ⏭ Group %s (annuaire) ignoré', $group->getSlug()));
                continue;
            }

            $mapping = $this->mappingRepository->findForGroup($group->getId());
            $newDn = $this->ldapService->groupDnFor($group->getSlug());
            $members = $this->resolveMemberUids($group);

            if ($mapping) {
                if ($mapping->getLdapDn() !== $newDn) {
                    if (!$dryRun) {
                        $this->ldapService->modifyRdn($mapping->getLdapDn(), 'cn=' . $this->normalizeForCn($group->getSlug()));
                        $mapping->setLdapDn($newDn);
                    }
                    $io->writeln(sprintf('  ↻ Group %s : MODRDN', $group->getSlug()));
                }

                if (!$dryRun) {
                    $this->ldapService->modify($newDn, [
                        'description' => $group->getDescription() ?? '',
                        'memberuid'   => $members,
                    ]);
                    $mapping->setSyncedAt(new \DateTimeImmutable());
                }
                $io->writeln(sprintf('  ✓ Group %s updated', $group->getSlug()));
            } else {
                $gidNumber = $group->getId() + 1000;
                if (!$dryRun) {
                    $ok = $this->ldapService->add($newDn, [
                        'objectclass' => ['top', 'posixGroup'],
                        'cn' => $this->normalizeForCn($group->getSlug()),
                        'gidnumber' => (string) $gidNumber,
                        'description' => $group->getDescription() ?? '',
                        'memberuid' => $members,
                    ]);

                    if ($ok) {
                        $mapping = new LdapMapping();
                        $mapping->setEntityType(LdapMapping::TYPE_GROUP);
                        $mapping->setEntityId($group->getId());
                        $mapping->setLdapDn($newDn);
                        $mapping->setLdapGidNumber($gidNumber);
                        $mapping->setSyncedAt(new \DateTimeImmutable());
                        $this->em->persist($mapping);
                    }
                }
                $io->writeln(sprintf('  + Group %s added', $group->getSlug()));
            }
        }
    }

    private function syncUsers(SymfonyStyle $io, bool $dryRun, ?int $userId): void
    {
        $criteria = [];
        if ($userId !== null) {
            $criteria['id'] = $userId;
        }

        $users = $this->userRepository->findBy($criteria);
        foreach ($users as $user) {
            // APP_ADMIN n'est pas skip : on le pousse pour permettre le bind admin.
            // Le rôle reste intouchable côté logique métier (ne peut pas être déclassé).

            $mapping = $this->mappingRepository->findForUser($user->getId());
            $newDn = $this->ldapService->userDnFor($user->getUsername());

            if ($mapping) {
                if ($mapping->getLdapDn() !== $newDn) {
                    if (!$dryRun) {
                        $this->ldapService->modifyRdn($mapping->getLdapDn(), 'uid=' . $this->normalizeForUid($user->getUsername()));
                        $mapping->setLdapDn($newDn);
                    }
                    $io->writeln(sprintf('  ↻ User %s : MODRDN', $user->getUsername()));
                }

                if (!$dryRun) {
                    $this->ldapService->modify($newDn, [
                        'mail'      => $user->getEmail() ?? '',
                        'givenname' => $user->getFirstname() ?? '',
                        'sn'        => $user->getLastname() ?? '',
                    ] + $this->ldapPasswordAttrs($user));
                    $mapping->setSyncedAt(new \DateTimeImmutable());
                }
                $io->writeln(sprintf('  ✓ User %s updated', $user->getUsername()));
            } else {
                $uidNumber = $user->getId() + 1000;
                if (!$dryRun) {
                    $ok = $this->ldapService->add($newDn, [
                        'objectclass'   => ['top', 'inetOrgPerson', 'posixAccount'],
                        'uid'           => $this->normalizeForUid($user->getUsername()),
                        'cn'            => trim(($user->getFirstname() ?? '') . ' ' . ($user->getLastname() ?? '')) ?: $this->normalizeForUid($user->getUsername()),
                        'uidnumber'     => (string) $uidNumber,
                        'gidnumber'     => '1000',
                        'mail'          => $user->getEmail() ?? '',
                        'sn'            => $user->getLastname() ?? $user->getUsername(),
                        'homedirectory' => '/home/' . $this->normalizeForUid($user->getUsername()),
                        'loginshell'    => '/bin/bash',
                    ] + $this->ldapPasswordAttrs($user));

                    if ($ok) {
                        $mapping = new LdapMapping();
                        $mapping->setEntityType(LdapMapping::TYPE_USER);
                        $mapping->setEntityId($user->getId());
                        $mapping->setLdapDn($newDn);
                        $mapping->setLdapUid($user->getUsername());
                        $mapping->setSyncedAt(new \DateTimeImmutable());
                        $this->em->persist($mapping);
                    }
                }
                $io->writeln(sprintf('  + User %s added', $user->getUsername()));
            }
        }
    }

    private function purgeOrphans(SymfonyStyle $io, bool $dryRun): void
    {
        foreach ($this->mappingRepository->findAllGroups() as $mapping) {
            $group = $this->groupRepository->find($mapping->getEntityId());
            if ($group && !$group->isAnnuaire()) {
                continue;
            }
            // Groupe manquant en BDD ou désormais annuaire : on supprime côté LDAP
            if (!$dryRun) {
                $this->ldapService->delete($mapping->getLdapDn());
                $this->em->remove($mapping);
            }
            $io->writeln(sprintf('  🗑 Group orphan LDAP deleted: %s', $mapping->getLdapDn()));
        }

        foreach ($this->mappingRepository->findAllUsers() as $mapping) {
            $user = $this->userRepository->find($mapping->getEntityId());
            if ($user) {
                // User existe encore en BDD → on garde le mapping
                continue;
            }
            if (!$dryRun) {
                $this->ldapService->delete($mapping->getLdapDn());
                $this->em->remove($mapping);
            }
            $io->writeln(sprintf('  🗑 User orphan LDAP deleted: %s', $mapping->getLdapDn()));
        }
    }

    /**
     * @return string[] Liste des uid LDAP (normalisés) des membres actifs du groupe.
     */
    private function resolveMemberUids(AppGroup $group): array
    {
        $uids = [];
        foreach ($group->getUserGroups() as $userGroup) {
            if ($userGroup->getRole() === UserGroup::ROLE_MASTER) {
                $user = $userGroup->getUser();
                if ($user && null !== $user->getUsername()) {
                    $uids[] = $this->normalizeForUid($user->getUsername());
                }
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

    /**
     * Retourne les attributs LDAP de mot de passe si disponible.
     * Si User::ldapPassword est null, on n'envoie rien (l'utilisateur LDAP
     * ne pourra pas se bind mais reste authentifiable via ninegate SQL).
     */
    /**
     * Retourne les attributs LDAP de mot de passe pour OpenLDAP Alpine.
     * Utilise le format `{CRYPT}$6$` (SHA-512 crypt natif) stocké dans
     * User::openLdapPassword, qui est ce que la glibc/musl Alpine supporte
     * sans compilation de module bcrypt.
     */
    private function ldapPasswordAttrs(User $user): array
    {
        $ldapHash = $user->getOpenLdapPassword();
        if (null === $ldapHash || '' === $ldapHash) {
            return [];
        }
        return ['userpassword' => $ldapHash];
    }

    private function normalizeForCn(string $value): string
    {
        $value = preg_replace('/[,+"\\\\<>=;#]/u', '_', $value);
        return trim($value);
    }
}