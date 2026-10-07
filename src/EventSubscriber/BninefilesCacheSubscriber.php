<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BninefilesCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité basse pour s'exécuter APRÈS AbstractSessionListener
            // (qui est sur kernel.response à priorité 0 par défaut).
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/bninefiles/')) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        // Déterminer le max-age selon le path
        $maxAge = $this->resolveMaxAge($path);

        if (null === $maxAge) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge($maxAge);
    }

    private function resolveMaxAge(string $path): ?int
    {
        // /bninefiles/thumbnail/* : cache long (1 an) car les thumbs sont régénérés uniquement si absents
        if (str_starts_with($path, '/bninefiles/thumbnail/')) {
            return 31536000;
        }

        // /bninefiles/image/* : cache 30 jours
        if (str_starts_with($path, '/bninefiles/image/')) {
            return 2592000;
        }

        return null;
    }
}