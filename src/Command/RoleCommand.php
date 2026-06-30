<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:role',
    description: 'Affecter un ou plusieurs rôles à un utilisateur',
)]
class RoleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Le nom de l\'utilisateur')
            ->addArgument('roles', InputArgument::IS_ARRAY, 'Les rôles à affecter (séparés par un espace)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $roles = $input->getArgument('roles');

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error(sprintf('L\'utilisateur "%s" n\'a pas été trouvé.', $username));

            return Command::FAILURE;
        }

        $user->setRoles($roles);
        $this->em->flush();

        $io->success(sprintf('Les rôles pour l\'utilisateur "%s" ont été mis à jour : %s', $username, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
