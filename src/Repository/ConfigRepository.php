<?php

namespace App\Repository;

use App\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Config>
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function findOneByCode(string $code): ?Config
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findByGroup(string $group): array
    {
        return $this->findBy(['configGroup' => $group], ['order' => 'ASC']);
    }

    public function findActiveByGroup(string $group, array $masterConfigValues = []): array
    {
        $configs = $this->findByGroup($group);

        return array_filter($configs, function (Config $config) use ($masterConfigValues) {
            $masterValue = $masterConfigValues[$config->getConfigMasterCode()] ?? null;

            return $config->isActive($masterValue);
        });
    }
}