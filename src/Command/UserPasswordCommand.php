<?php

namespace App\Command;

use App\Message\UserSyncMessage;
use App\Repository\UserRepository;
use App\Service\LdapPasswordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:password',
    description: 'Change le mot de passe d\'un utilisateur',
)]
class UserPasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LdapPasswordService $ldapPasswordService,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username de l\'utilisateur')
            ->setDescription('Change le mot de passe d\'un utilisateur et synchronise le hash LDAP (compatible GLAuth et OpenLDAP).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            $output->writeln("<error>Utilisateur '$username' introuvable.</error>");
            return Command::FAILURE;
        }

        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        $password = $io->askHidden('Nouveau mot de passe : ');

        if (strlen($password) < 8) {
            $output->writeln('<error>Le mot de passe doit contenir au moins 8 caractères.</error>');
            return Command::FAILURE;
        }

        // Hash bcrypt (auth ninegate)
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Hash {BCRYPT} (push vers OpenLDAP / GLAuth)
        $user->setLdapPassword($this->ldapPasswordService->hashForLdap($password));
        $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($password));

        $user->setNeedsPasswordUpgrade(false);

        $this->em->flush();

        $output->writeln("<info>Mot de passe de '$username' mis à jour avec succès.</info>");
        $output->writeln("<info>  - bcrypt (auth ninegate): OK</info>");
        $output->writeln("<info>  - {BCRYPT} (OpenLDAP/GLAuth): OK</info>");

        $this->bus->dispatch(new UserSyncMessage($user->getId()));

        return Command::SUCCESS;
    }
}