<?php

namespace App\Command;

use App\Repository\Ldap\LdapGroupRepository;
use App\Repository\Ldap\LdapUserRepository;
use App\Repository\Ldap\LdapCapabilityRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ldap:verify',
    description: 'Vérifie la synchronisation entre les données app et les tables LDAP',
)]
class LdapVerifyCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private LdapGroupRepository $ldapGroupRepo,
        private LdapUserRepository $ldapUserRepo,
        private LdapCapabilityRepository $ldapCapabilityRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Supprimer les entrées LDAP orphelines');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LDAP VERIFY');
        $io->text('Vérification de la synchronisation LDAP');
        $io->text('');

        $fix = $input->getOption('fix');
        $issues = [];

        // ── Users manquants dans LDAP ──
        $appUsers = $this->userRepository->findAll();
        $ldapUsers = $this->ldapUserRepo->findAll();
        $io->text('Users : ' . count($ldapUsers) . ' LDAP / ' . count($appUsers) . ' App');

        foreach ($appUsers as $user) {
            if (!$this->ldapUserRepo->findOneBy(['uidnumber' => $user->getId() + 1000])) {
                $issues[] = "User '{$user->getUsername()}' manquant dans LDAP";
            }
        }

        // ── Users orphelins dans LDAP (supprimés de l'app) ──
        $ldapOrphanUsers = [];
        foreach ($ldapUsers as $ldapUser) {
            $appId = $ldapUser->getUidnumber() - 1000;
            if (!$this->userRepository->find($appId)) {
                $ldapOrphanUsers[] = $ldapUser;
                $issues[] = "LDAP user '{$ldapUser->getName()}' (uid={$ldapUser->getUidnumber()}) orphelin — plus dans l'app";
            }
        }

        // ── Groups manquants dans LDAP ──
        $ldapGroups = $this->ldapGroupRepo->findAll();
        $io->text('Groups : ' . count($ldapGroups) . ' LDAP');

        // ── Groups orphelins dans LDAP ──
        $ldapOrphanGroups = [];
        foreach ($ldapGroups as $ldapGroup) {
            $appId = $ldapGroup->getGidnumber() - 1000;
            if (!$this->em->getRepository(\App\Entity\Group::class)->find($appId)) {
                $ldapOrphanGroups[] = $ldapGroup;
                $issues[] = "LDAP group '{$ldapGroup->getName()}' (gid={$ldapGroup->getGidnumber()}) orphelin — plus dans l'app";
            }
        }

        // ── Capabilities ──
        $capabilities = $this->ldapCapabilityRepo->findAll();
        $io->text('Capabilities : ' . count($capabilities) . ' entries');

        // ── Résultat ──
        if (empty($issues)) {
            $io->success('LDAP synchronisé correctement');
        } else {
            $io->warning(count($issues) . ' problème(s) trouvé(s)');
            foreach ($issues as $issue) {
                $io->text('  ⚠ ' . $issue);
            }

            if ($fix) {
                $io->text('');
                $io->text('Suppression des entrées orphelines...');

                foreach ($ldapOrphanUsers as $user) {
                    $this->em->remove($user);
                    $io->text('  ✗ LDAP user supprimé : ' . $user->getName());
                }
                foreach ($ldapOrphanGroups as $group) {
                    $this->em->remove($group);
                    $io->text('  ✗ LDAP group supprimé : ' . $group->getName());
                }

                $this->em->flush();
                $io->success('Nettoyage effectué');
            } else {
                $io->text('');
                $io->text('Utilisez --fix pour supprimer les entrées orphelines');
            }
        }

        return Command::SUCCESS;
    }
}
