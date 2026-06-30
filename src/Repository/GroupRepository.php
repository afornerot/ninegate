<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findOpenGroupsNotInList(array $excludeIds): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.isOpen = :isOpen')
            ->setParameter('isOpen', true);

        if (!empty($excludeIds)) {
            $qb->andWhere($qb->expr()->notIn('g.id', ':ids'))
               ->setParameter('ids', $excludeIds);
        }

        return $qb->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}