<?php

namespace App\EventSubscriber;

use App\Service\ConfigParameterBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ConfigParameterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ConfigParameterBag $configParameterBag
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    public function onKernelRequest(): void
    {
        $this->configParameterBag->load();
    }
}