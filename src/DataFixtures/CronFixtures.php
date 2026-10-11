<?php

namespace App\DataFixtures;

use App\Entity\Cron;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CronFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $crons = [
            'app:identity:sync' => [
                'description' => 'Synchronisation identité (dispatch selon SYNC_IDENTITY)',
                'statut' => Cron::STATUT_TODO,
                'repeatcall' => 0,
                'repeatexec' => 0,
                'repeatinterval' => 86400,
            ],
        ];

        foreach ($crons as $command => $item) {
            $cron = $manager->getRepository(Cron::class)->findOneBy(['command' => $command]);
            if ($cron) {
                continue;
            }

            $cron = new Cron();
            $cron->setCommand($command);
            $cron->setDescription($item['description']);
            $cron->setStatut($item['statut']);
            $cron->setRepeatcall($item['repeatcall']);
            $cron->setRepeatexec($item['repeatexec']);
            $cron->setRepeatinterval($item['repeatinterval']);
            $cron->setNextexecdate((new \DateTime())->setTime(5, 0));

            $manager->persist($cron);
        }

        $manager->flush();
    }
}
