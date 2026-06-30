<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListener
{
    private ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogoutEvent(LogoutEvent $event): void
    {
        if ('CAS' == $this->parameterBag->get('appModeAuth')) {
            $request = $event->getRequest();
            $host = $request->headers->get('host');
            $scheme = $request->headers->get('X-Forwarded-Proto') ?? $request->getScheme();
            $url = $scheme.'://'.$host;
            \phpCAS::client(CAS_VERSION_2_0, $this->parameterBag->get('casHost'), (int) $this->parameterBag->get('casPort'), $this->parameterBag->get('casPath'), $url, false);
            \phpCAS::setNoCasServerValidation();
            
            $url.=$request->getBaseUrl();
            \phpCAS::logoutWithRedirectService($url);

        }
    }
}
