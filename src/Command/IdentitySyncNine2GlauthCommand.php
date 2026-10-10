<?php

namespace App\Command;

use App\Entity\Ldap\LdapGroup;
use App\Entity\Ldap\LdapUser;
use App\Entity\UserGroup;
use App\Repository\Ldap\LdapCapabilityRepository;
use App\Repository\Ldap\LdapGroupRepository;
use App\Repository\Ldap\LdapUserRepository;
use App\Repository\UserRepository;
use App\Service\IdentityProvider;
use App\Service\LdapPasswordService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * SENS : ninegate → GLAuth (vue Postgres via plugin LDAP)
 *
 * Rappel : GLAuth n'est PAS un vrai OpenLDAP, c'est un adaptateur Postgres
 * qui expose une vue LDAP aux clients. Ce flux peuple les tables Ldap*
 * consommées par glauth-plugins.
 *
 * IMPORTANT : Ninegate est souverain sur les rôles applicatifs (ROLE_ADMIN,
 * ROLE_MASTER, ROLE_USER). Le gidnumber LDAP mappé ici est un groupe POSIX
 * (technique), sans lien avec les rôles Symfony.
 */
#[AsCommand(
    name: 'app:identity:nine2glauth',
    description: 'Pousse les données ninegate vers les tables Ldap* (vue GLAuth)',
)]
class IdentitySyncNine2GlauthCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private LdapGroupRepository $ldapGroupRepo,
        private LdapUserRepository $ldapUserRepo,
        private LdapCapabilityRepository $ldapCapabilityRepo,
        private Connection $connection,
        private IdentityProvider $identityProvider,
        private LdapPasswordService $ldapPasswordService,
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
        if (!$this->identityProvider->isSyncNineToGlauth()) {
            $output->writeln('<error>SYNC_IDENTITY ne concorde pas avec cette commande (attendu: NINE2GLAUTH).</error>');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('SYNC NINE → GLAUTH');
        $io->text('Pousse les données ninegate vers les tables Ldap* (vue GLAuth)');
        $io->text('');

        $this->syncGroups($io);
        $this->syncUsers($io);
        $this->syncCapabilities($io);

        $io->text('');
        $io->success('GLAuth synchronisé');
        return Command::SUCCESS;
    }

    private function syncGroups(SymfonyStyle $io): void
    {
        $groups = $this->em->getRepository(\App\Entity\Group::class)->findAll();
        foreach ($groups as $group) {
            $ldapGroup = $this->ldapGroupRepo->findOneBy(['gidnumber' => $group->getId() + 1000]);
            if (!$ldapGroup) {
                $ldapGroup = new LdapGroup();
            }
            $ldapGroup->setName($group->getSlug());
            $ldapGroup->setGidnumber($group->getId() + 1000);
            $this->em->persist($ldapGroup);
        }

        // Groupe "users" par défaut — requis par glauth v2.4.0 pour les bindDN
        // de type uid=X,ou=users,Y (DN attendu par le parseur Go).
        $defaultUsers = $this->ldapGroupRepo->findOneBy(['gidnumber' => 1000]);
        if (!$defaultUsers) {
            $defaultUsers = new LdapGroup();
            $defaultUsers->setName('users');
            $defaultUsers->setGidnumber(1000);
            $this->em->persist($defaultUsers);
        } else {
            $defaultUsers->setName('users');
            $this->em->persist($defaultUsers);
        }

        $this->em->flush();
        $io->text('    ✓ ldapgroups synchronisées');
    }

    private function syncUsers(SymfonyStyle $io): void
    {
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $ldapUser = $this->ldapUserRepo->findOneBy(['uidnumber' => $user->getId() + 1000]);
            if (!$ldapUser) {
                $ldapUser = new LdapUser();
            }

            $primaryGroupGid = 1000;
            $otherGroupIds = [];
            foreach ($user->getUserGroups() as $ug) {
                $gid = $ug->getGroup()->getId() + 1000;
                if ($ug->getRole() === UserGroup::ROLE_MASTER && $primaryGroupGid === 1000) {
                    $primaryGroupGid = $gid;
                } else {
                    $otherGroupIds[] = $gid;
                }
            }
            if ($primaryGroupGid === 1000 && !empty($otherGroupIds)) {
                $primaryGroupGid = array_shift($otherGroupIds);
            }
            $otherGroupIds = array_values(array_diff($otherGroupIds, [$primaryGroupGid]));

            $ldapUser->setName($user->getUsername());
            $ldapUser->setUidnumber($user->getId() + 1000);
            $ldapUser->setPrimarygroup($primaryGroupGid);
            $ldapUser->setOthergroups(!empty($otherGroupIds) ? implode(',', $otherGroupIds) : '');
            $ldapUser->setGivenname($user->getFirstname() ?? '');
            $ldapUser->setSn($user->getLastname() ?? '');
            $ldapUser->setMail($user->getEmail() ?? '');

            if ($user->getLdapPassword()) {
                $ldapUser->setPassbcrypt($this->ldapPasswordService->hashForGlauth($user->getLdapPassword()));
            }

            $ldapUser->setDisabled(0);
            $ldapUser->setSshkeys('');
            $this->em->persist($ldapUser);
        }
        $this->em->flush();
        $io->text('    ✓ users synchronisés');
    }

    private function syncCapabilities(SymfonyStyle $io): void
    {
        $ldapBase = 'dc=ninegate,dc=local';

        $this->connection->executeStatement('DELETE FROM capabilities');
        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'search', '$ldapBase' FROM app_user u JOIN user_group ug ON ug.user_id = u.id WHERE ug.role IN ('MASTER', 'USER')");
        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'add', LOWER(CONCAT('ou=', g.slug, ',$ldapBase')) FROM app_user u JOIN user_group ug ON ug.user_id = u.id JOIN app_group g ON g.id = ug.group_id WHERE ug.role = 'MASTER'");
        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'modify', LOWER(CONCAT('ou=', g.slug, ',$ldapBase')) FROM app_user u JOIN user_group ug ON ug.user_id = u.id JOIN app_group g ON g.id = ug.group_id WHERE ug.role = 'MASTER'");

        $io->text('    ✓ capabilities peuplées');
    }
}