<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init',
    description: 'Initialisation de l\'application',
)]
class InitCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('APP:INIT');
        $io->text('Initialisation of the app');
        $io->text('');

        $io->text('> Chargement des fixtures');
        $command = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [
            '--append' => true,
        ];
        $greetInput = new ArrayInput($arguments);
        $command->run($greetInput, $output);

        return Command::SUCCESS;
    }
}
