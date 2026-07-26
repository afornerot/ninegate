<?php

namespace App\Repository;

use App\Entity\Calendar;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Calendar>
 */
class CalendarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendar::class);
    }

    public function findAccessibleCalendars(?User $user): array
    {
        $calendars = $this->findAll();
        return array_filter($calendars, fn($c) => $this->isCalendarAccessibleForUser($c, $user));
    }

    public function isCalendarAccessibleForUser(Calendar $calendar, ?User $user): bool
    {
        $userRoles = $user ? $user->getRoles() : ['ROLE_VISITOR'];

        if ($user && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        if ($user && $calendar->getUser() && $calendar->getUser()->getId() === $user->getId()) {
            return true;
        }

        if (!empty($calendar->getRoles())) {
            foreach ($userRoles as $role) {
                if (in_array($role, $calendar->getRoles())) {
                    return true;
                }
            }
        }

        if (!$user) {
            return false;
        }

        foreach ($calendar->getGroups() as $group) {
            if ($group->getUsers()->contains($user)) {
                return true;
            }
        }
        return false;
    }

    public function isCalendarOwnerOrGroupMaster(Calendar $calendar, User $user): bool
    {
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }
        foreach ($calendar->getGroups() as $group) {
            $ug = $group->getUserGroup($user);
            if ($ug && UserGroup::ROLE_MASTER === $ug->getRole()) {
                return true;
            }
        }
        return false;
    }

    public function isCalendarUserOrAbove(Calendar $calendar, User $user): bool
    {
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }
        foreach ($calendar->getGroups() as $group) {
            $ug = $group->getUserGroup($user);
            if ($ug && in_array($ug->getRole(), [UserGroup::ROLE_MASTER, UserGroup::ROLE_USER])) {
                return true;
            }
        }
        return false;
    }

    public function findCalendarsByGroups(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }
        $groupIds = array_map(fn(Group $g) => $g->getId(), $groups);
        return $this->createQueryBuilder('c')
            ->innerJoin('c.groups', 'g')
            ->where('g.id IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->orderBy('c.calendarOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
