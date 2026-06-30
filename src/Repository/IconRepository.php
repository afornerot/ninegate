<?php

namespace App\Repository;

use App\Entity\Icon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Icon>
 */
class IconRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Icon::class);
    }

    public function findOneByRoute(string $route): ?Icon
    {
        return $this->findOneBy(['route' => $route]);
    }
}