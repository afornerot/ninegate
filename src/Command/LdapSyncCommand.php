<?php

namespace App\Command;

use App\Entity\Ldap\LdapGroup;
use App\Entity\Ldap\LdapUser;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\Ldap\LdapGroupRepository;
use App\Repository\Ldap\LdapUserRepository;
use App\Repository\Ldap\LdapCapabilityRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ldap:sync',
    description: 'Synchronise les données app vers les tables LDAP',
)]
class LdapSyncCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private LdapGroupRepository $ldapGroupRepo,
        private LdapUserRepository $ldapUserRepo,
        private LdapCapabilityRepository $ldapCapabilityRepo,
        private \Doctrine\DBAL\Connection $connection,
        private ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LDAP SYNC');
        $io->text('Synchronisation des données app vers les tables LDAP');
        $io->text('');

        $this->syncGroups($io);
        $this->syncUsers($io);
        $this->syncCapabilities($io);

        $io->text('');
        $io->success('LDAP synchronisé avec succès');

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
            $ldapGroup->setName($group->getName());
            $ldapGroup->setGidnumber($group->getId() + 1000);
            $this->em->persist($ldapGroup);
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
            $ldapUser->setPassbcrypt(bin2hex($user->getPassword()));
            $ldapUser->setDisabled(0);
            $ldapUser->setSshkeys('');
            $this->em->persist($ldapUser);
        }

        $this->em->flush();
        $io->text('    ✓ users synchronisés');
    }

    private function syncCapabilities(SymfonyStyle $io): void
    {
        $ldapBase = $this->parameterBag->get('ldapBase');

        $this->connection->executeStatement('DELETE FROM capabilities');

        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'search', '$ldapBase' FROM user u JOIN user_group ug ON ug.user_id = u.id WHERE ug.role IN ('MASTER', 'USER')");
        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'add', LOWER(CONCAT('ou=', g.name, ',$ldapBase')) FROM user u JOIN user_group ug ON ug.user_id = u.id JOIN `group` g ON g.id = ug.group_id WHERE ug.role = 'MASTER'");
        $this->connection->executeStatement("INSERT INTO capabilities (userid, action, object) SELECT DISTINCT u.id + 1000, 'modify', LOWER(CONCAT('ou=', g.name, ',$ldapBase')) FROM user u JOIN user_group ug ON ug.user_id = u.id JOIN `group` g ON g.id = ug.group_id WHERE ug.role = 'MASTER'");

        $io->text('    ✓ capabilities peuplées');
    }
}
