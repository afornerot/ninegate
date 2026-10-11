<?php

namespace App\Repository;

use App\Entity\Cron;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cron>
 */
class CronRepository extends ServiceEntityRepository
{
    private const STALE_TIMEOUT = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cron::class);
    }

    /**
     * @return array<int, Cron>
     */
    public function toExec(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('cron')
            ->where('cron.statut = :todo')
            ->orWhere('cron.statut = :ok AND cron.nextexecdate < :now AND cron.repeatcall = 0')
            ->orWhere('cron.statut = :ko AND cron.nextexecdate < :now AND cron.repeatcall = 0')
            ->orWhere('cron.statut = :ko AND cron.nextexecdate < :now AND cron.repeatcall > cron.repeatexec')
            ->setParameter('todo', Cron::STATUT_TODO)
            ->setParameter('ok', Cron::STATUT_OK)
            ->setParameter('ko', Cron::STATUT_KO)
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->getQuery()
            ->getResult();
    }

    public function resetStale(): void
    {
        $limit = new \DateTime();
        $limit->modify('-'.self::STALE_TIMEOUT.' seconds');

        $this->createQueryBuilder('cron')
            ->update()
            ->set('cron.statut', ':todo')
            ->set('cron.startexecdate', ':start')
            ->where('cron.statut = :running')
            ->andWhere('cron.startexecdate < :limit')
            ->setParameter('todo', Cron::STATUT_TODO)
            ->setParameter('running', Cron::STATUT_RUNNING)
            ->setParameter('start', null)
            ->setParameter('limit', $limit->format('Y-m-d H:i:s'))
            ->getQuery()
            ->execute();
    }
}
