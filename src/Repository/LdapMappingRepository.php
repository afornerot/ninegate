<?php

namespace App\Repository;

use App\Entity\LdapMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LdapMapping>
 */
class LdapMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LdapMapping::class);
    }

    public function findForUser(int $userId): ?LdapMapping
    {
        return $this->findOneBy([
            'entityType' => LdapMapping::TYPE_USER,
            'entityId' => $userId,
        ]);
    }

    public function findForGroup(int $groupId): ?LdapMapping
    {
        return $this->findOneBy([
            'entityType' => LdapMapping::TYPE_GROUP,
            'entityId' => $groupId,
        ]);
    }

    public function findByDn(string $dn): ?LdapMapping
    {
        return $this->findOneBy(['ldapDn' => $dn]);
    }

    /**
     * @return LdapMapping[]
     */
    public function findAllUsers(): array
    {
        return $this->findBy(['entityType' => LdapMapping::TYPE_USER]);
    }

    /**
     * @return LdapMapping[]
     */
    public function findAllGroups(): array
    {
        return $this->findBy(['entityType' => LdapMapping::TYPE_GROUP]);
    }
}