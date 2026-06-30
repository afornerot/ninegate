<?php

namespace App\EventSubscriber;

use App\Service\PageParameterBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class PageParameterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageParameterBag $pageParameterBag
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10],
        ];
    }

    public function onKernelController(): void
    {
        $this->pageParameterBag->load();
    }
}