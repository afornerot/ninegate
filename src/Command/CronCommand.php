<?php

namespace App\Command;

use App\Entity\Cron;
use App\Repository\CronRepository;
use App\Service\CronSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cron',
    description: 'Execution of the cron commands',
)]
class CronCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private CronRepository $cronRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            return Command::SUCCESS;
        }

        $this->cronRepository->resetStale();

        $crons = $this->cronRepository->toExec();
        if (!$crons) {
            return Command::SUCCESS;
        }

        $io->title('APP:CRON');
        $now = new \DateTime();
        $io->text('Date = '.$now->format('Y-m-d H:i:s'));

        $application = $this->getApplication();
        if (null === $application) {
            throw new \LogicException('Symfony Application instance is not available.');
        }

        foreach ($crons as $cron) {
            $idcron = $cron->getId();

            $commandName = $cron->getCommand();
            if (null === $commandName || '' === $commandName) {
                continue;
            }

            $io->text('Exécution de '.$commandName);

            $cron->setStartexecdate(new \DateTime());
            $this->em->flush();

            try {
                $command = $application->find($commandName);
            } catch (\InvalidArgumentException $e) {
                $io->error('Commande introuvable : '.$commandName.' — '.$e->getMessage());
                $cron = $this->cronRepository->find($idcron);
                if (null !== $cron) {
                    $cron->setEndexecdate(new \DateTime());
                    $cron->setStatut(Cron::STATUT_KO);
                    $cron->setRepeatexec(($cron->getRepeatexec() ?? 0) + 1);
                    $cron->setNextexecdate(CronSchedule::nextDate($cron->getRepeatinterval() ?? 0, new \DateTime(), $cron->getNextexecdate()));
                    $this->em->flush();
                }

                continue;
            }

            $arguments = json_decode((string) $cron->getJsonargument(), true);
            $parameter = new ArrayInput(is_array($arguments) ? $arguments : []);

            $success = true;
            try {
                $command->run($parameter, $output);
            } catch (\Throwable $e) {
                $success = false;
                $io->error('Erreur sur '.$commandName.' : '.$e->getMessage());
            }

            $cron = $this->cronRepository->find($idcron);
            if (null === $cron) {
                continue;
            }

            $cron->setEndexecdate(new \DateTime());

            if ($success) {
                $cron->setStatut(Cron::STATUT_OK);
                $cron->setRepeatexec(0);
            } else {
                $cron->setStatut(Cron::STATUT_KO);
                $cron->setRepeatexec(($cron->getRepeatexec() ?? 0) + 1);
            }

            $nextexecdate = $cron->getNextexecdate();
            $interval = $cron->getRepeatinterval() ?? 0;
            $cron->setNextexecdate(CronSchedule::nextDate($interval, new \DateTime(), $nextexecdate));

            $this->em->flush();
        }

        return Command::SUCCESS;
    }
}
