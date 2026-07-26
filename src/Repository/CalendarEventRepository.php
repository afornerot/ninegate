<?php

namespace App\Repository;

use App\Entity\CalendarEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarEvent>
 */
class CalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarEvent::class);
    }

    public function findAccessibleEvents(?User $user, int $limit): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.calendar', 'c')
            ->leftJoin('c.groups', 'g')
            ->leftJoin('g.userGroups', 'ug')
            ->where('ug.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit);

        $events = $qb->getQuery()->getResult();

        $seen = [];
        $result = [];
        foreach ($events as $event) {
            if (!isset($seen[$event->getId()])) {
                $seen[$event->getId()] = true;
                $result[] = $event;
            }
        }

        return $result;
    }

    public function findEventsByCalendars(array $calendars, ?User $user, int $limit): array
    {
        if (empty($calendars)) {
            return [];
        }

        $calendarIds = array_map(fn($c) => $c->getId(), $calendars);

        return $this->createQueryBuilder('e')
            ->where('e.calendar IN (:calendarIds)')
            ->setParameter('calendarIds', $calendarIds)
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findEventsByCalendarsForFullCalendar(array $calendars): array
    {
        if (empty($calendars)) {
            return [];
        }

        $calendarIds = array_map(fn($c) => $c->getId(), $calendars);

        return $this->createQueryBuilder('e')
            ->where('e.calendar IN (:calendarIds)')
            ->setParameter('calendarIds', $calendarIds)
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
