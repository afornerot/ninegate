<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag as SymfonyParameterBag;

class ConfigParameterBag extends SymfonyParameterBag
{
    public function __construct(
        private ConfigRepository $configRepository
    ) {}

    public function load(): void
    {
        $configs = $this->configRepository->findAll();
        $this->clear();
        $values = [];
        foreach ($configs as $config) {
            $values[$config->getCode()] = $config->getTypedValue();
        }
        $this->add($values);
    }

    public function reload(): void
    {
        $this->load();
    }
}