<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\CharteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CharteSignatureSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_PATHS = [
        '/user/charte/sign-required',
        '/user/charte/sign/',
        '/user/charte/sign-all',
        '/logout',
        '/login',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CharteRepository $charteRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (str_starts_with($pathInfo, '/_') || str_starts_with($pathInfo, '/css/') || str_starts_with($pathInfo, '/js/') || str_starts_with($pathInfo, '/images/') || str_starts_with($pathInfo, '/medias/') || str_starts_with($pathInfo, '/lib/') || str_starts_with($pathInfo, '/bundles/')) {
            return;
        }

        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_starts_with($pathInfo, $excludedPath)) {
                return;
            }
        }

        if (!$this->tokenStorage->getToken()?->getUser()) {
            return;
        }

        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user instanceof User) {
            return;
        }
        $chartes = $this->charteRepository->findBy(['requireSignature' => true], ['sortOrder' => 'ASC']);

        foreach ($chartes as $charte) {
            if ($charte->isAccessibleToUser($user) && !$charte->hasUserSigned($user)) {
                $response = new RedirectResponse($request->getSchemeAndHttpHost() . '/user/charte/sign-required');
                $event->setResponse($response);
                return;
            }
        }
    }
}
