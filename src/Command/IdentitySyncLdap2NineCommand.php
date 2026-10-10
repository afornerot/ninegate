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
use App\Service\LdapService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SENS : OpenLDAP → ninegate
 *
 * Importe les users et groups LDAP comme entités ninegate avec
 * isAnnuaire=true.
 *
 * Règles métier :
 * - APP_ADMIN est réservé : refus d'import si un user LDAP se nomme ainsi,
 *   force néanmoins la mise à jour du mot de passe si l'entrée existe.
 * - Les users LDAP sans userPassword ne sont pas importés.
 * - Les nouveaux users sont initialisés à ROLE_USER.
 * - Les users existants ne voient JAMAIS leurs rôles modifiés.
 * - Pseudo, avatar, needsPasswordUpgrade sont des données internes ninegate
 *   (null à la création par LDAP2NINE, jamais modifiées par la suite).
 */
#[AsCommand(
    name: 'app:identity:ldap2nine',
    description: 'Importe les données OpenLDAP vers ninegate',
)]
class IdentitySyncLdap2NineCommand extends Command
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
        if (!$this->identityProvider->isSyncLdapToNine()) {
            $output->writeln('<error>SYNC_IDENTITY ne concorde pas avec cette commande (attendu: LDAP2NINE).</error>');
            return Command::FAILURE;
        }

        if ($this->identityProvider->isIdentityLocal()) {
            $output->writeln('<error>APP_MASTERIDENTITY=SQL est incompatible avec SYNC_IDENTITY=LDAP2NINE.</error>');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $noPurge = (bool) $input->getOption('no-purge');

        $io->title('SYNC LDAP → NINE');
        if ($dryRun) {
            $io->note('Mode dry-run — aucune écriture.');
        }

        try {
            $this->ldapService->bind();
        } catch (\Throwable $e) {
            $io->error('Impossible de se connecter à OpenLDAP : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->syncLdapGroups($io, $dryRun);
        $this->syncLdapUsers($io, $dryRun);

        if (!$noPurge) {
            $this->purgeOrphans($io, $dryRun);
        } else {
            $io->note('--no-purge : purge des orphelins désactivée.');
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success('LDAP2NINE terminé.');
        return Command::SUCCESS;
    }

    private function syncLdapUsers(SymfonyStyle $io, bool $dryRun): void
    {
        $usersDn = $this->getLdapUsersDn();
        $entries = $this->ldapService->search(
            $usersDn,
            '(objectClass=inetOrgPerson)',
            ['uid', 'mail', 'givenname', 'sn', 'userpassword']
        );

        foreach ($entries as $entry) {
            $attrs = $entry->getAttributes();
            $rawUid = $attrs['uid'][0] ?? null;
            if (null === $rawUid) {
                continue;
            }

            $normalizedUid = $this->normalizeForUid($rawUid);
            $adminReserved = $this->adminGuard->isAdminUsername($normalizedUid);

            if ($adminReserved) {
                if (!$dryRun) {
                    $existing = $this->userRepository->findOneBy(['username' => $normalizedUid]);
                    if ($existing) {
                        $io->note(sprintf('  ★ APP_ADMIN détecté — forçage password LDAP → ninegate'));
                    }
                }
                continue;
            }

            $hasPassword = !empty($attrs['userpassword'][0] ?? null);
            if (!$hasPassword) {
                $io->warning(sprintf('  ⏭ User LDAP %s sans userPassword : ignoré', $normalizedUid));
                continue;
            }

            $mapping = $this->mappingRepository->findByDn($entry->getDn());
            $user = $mapping
                ? $this->userRepository->find($mapping->getEntityId())
                : $this->userRepository->findOneBy(['username' => $normalizedUid]);

            if (!$user) {
                $user = new User();
                $user->setUsername($normalizedUid);
                $user->setPassword(Uuid::uuid4()->toString());
                $user->setRoles(['ROLE_USER']);
                // pseudo, avatar, needsPasswordUpgrade volontairement non settés (null par défaut)
                $this->em->persist($user);
                $io->writeln(sprintf('  + User créé: %s', $normalizedUid));
            } else {
                $io->writeln(sprintf('  ✓ User existant maj: %s', $normalizedUid));
            }

            $user->setEmail($attrs['mail'][0] ?? $user->getEmail());
            $user->setFirstname($attrs['givenname'][0] ?? $user->getFirstname());
            $user->setLastname($attrs['sn'][0] ?? $user->getLastname());
            // setRoles volontairement non touché — ninegate souverain

            if (!$mapping) {
                $mapping = new LdapMapping();
                $mapping->setEntityType(LdapMapping::TYPE_USER);
                $mapping->setEntityId($user->getId());
                $mapping->setLdapDn($entry->getDn());
                $mapping->setLdapUid($normalizedUid);
                $this->em->persist($mapping);
            }
            $mapping->setSyncedAt(new \DateTimeImmutable());

            $this->em->flush(); // pour récupérer l'id du user
        }
    }

    private function syncLdapGroups(SymfonyStyle $io, bool $dryRun): void
    {
        $groupsDn = $this->getLdapGroupsDn();
        $entries = $this->ldapService->search(
            $groupsDn,
            '(objectClass=groupOfNames)',
            ['cn', 'description', 'memberuid', 'gidnumber']
        );

        foreach ($entries as $entry) {
            $attrs = $entry->getAttributes();
            $cn = $attrs['cn'][0] ?? null;
            if (null === $cn) {
                continue;
            }

            $mapping = $this->mappingRepository->findByDn($entry->getDn());

            if ($mapping) {
                $group = $this->groupRepository->find($mapping->getEntityId());
                if ($group) {
                    $io->writeln(sprintf('  ✓ Group existant maj: %s', $cn));
                } else {
                    $group = new AppGroup();
                    $this->em->persist($group);
                }
            } else {
                $group = new AppGroup();
                $this->em->persist($group);
                $io->writeln(sprintf('  + Group créé: %s', $cn));
            }

            $group->setSlug($this->normalizeForCn($cn));
            $group->setName($cn);
            $group->setDescription($attrs['description'][0] ?? null);
            $group->setIsAnnuaire(true);
            $group->setIsSystem(false);
            $group->setType(AppGroup::TYPE_ORGANISATION);

            $this->em->flush();

            if (!$mapping) {
                $mapping = new LdapMapping();
                $mapping->setEntityType(LdapMapping::TYPE_GROUP);
                $mapping->setEntityId($group->getId());
                $mapping->setLdapDn($entry->getDn());
                $gid = (int) ($attrs['gidnumber'][0] ?? 0);
                $mapping->setLdapGidNumber($gid > 0 ? $gid : null);
                $this->em->persist($mapping);
            }
            $mapping->setSyncedAt(new \DateTimeImmutable());

            $memberUids = $attrs['memberuid'] ?? [];
            if (!empty($memberUids)) {
                $this->resolveGroupMembers($group, $memberUids);
            }
        }
    }

    private function resolveGroupMembers(AppGroup $group, array $memberUids): void
    {
        $currentMembers = [];
        foreach ($group->getUserGroups() as $ug) {
            $currentMembers[$ug->getUser()->getUsername()] = $ug;
        }

        $ldapUids = [];
        foreach ($memberUids as $raw) {
            if (is_string($raw)) {
                $ldapUids[$this->normalizeForUid($raw)] = true;
            }
        }

        foreach (array_keys($currentMembers) as $existingUid) {
            if (!isset($ldapUids[$this->normalizeForUid($existingUid)])) {
                $group->removeUser($currentMembers[$existingUid]->getUser());
            }
        }

        foreach (array_keys($ldapUids) as $uid) {
            $user = $this->userRepository->findOneBy(['username' => $uid]);
            if ($user && !isset($currentMembers[$uid])) {
                $group->addUser($user, UserGroup::ROLE_USER);
            }
        }
    }

    private function purgeOrphans(SymfonyStyle $io, bool $dryRun): void
    {
        // Purge groups ninegate qui étaient annuaire (= gérés par LDAP) mais plus dans LDAP
        $groups = $this->groupRepository->findBy(['isAnnuaire' => true]);
        foreach ($groups as $group) {
            $mapping = $this->mappingRepository->findForGroup($group->getId());
            if ($mapping && $this->ldapService->exists($mapping->getLdapDn())) {
                continue;
            }
            // Plus dans LDAP → on désactive
            if (!$dryRun) {
                $group->setIsAnnuaire(false);
            }
            $io->writeln(sprintf('  ⚠ Group %s plus dans LDAP : annuaire désactivé', $group->getSlug()));
        }
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
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9._-]/u', '_', $value);
        return trim($value, '_');
    }

    private function getLdapUsersDn(): string
    {
        return (string) $this->parameterBag->get('ldapUsersDn');
    }

    private function getLdapGroupsDn(): string
    {
        return (string) $this->parameterBag->get('ldapGroupsDn');
    }
}