<?php

namespace App\Controller;

use App\CalDAV\CalendarBackend;
use App\CalDAV\PrincipalBackend;
use App\CalDAV\AuthBackend;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CalendarEventRepository;
use App\Repository\CalendarRepository;
use App\Repository\UserRepository;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Principal\Collection as PrincipalCollection;
use Sabre\DAV\Server;
use Sabre\DAV\SimpleCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalDAVController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private CalendarRepository $calendarRepository,
        private CalendarEventRepository $calendarEventRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/caldav', name: 'app_caldav')]
    #[Route('/caldav/{path}', name: 'app_caldav_path', requirements: ['path' => '.*'])]
    public function __invoke(?string $path = null): Response
    {
        $authBackend = new AuthBackend($this->userRepository);
        $principalBackend = new PrincipalBackend($this->userRepository);
        $calendarBackend = new CalendarBackend(
            $this->calendarRepository,
            $this->calendarEventRepository,
            $this->userRepository,
            $this->em
        );

        $principalCollection = new PrincipalCollection($principalBackend, 'principals');

        $calendarRoot = new CalendarRoot(
            $principalBackend,
            $calendarBackend,
            'principals/'
        );

        $root = new SimpleCollection('root', [
            $principalCollection,
            $calendarRoot,
        ]);

        $server = new Server($root);
        $server->setBaseUri('/caldav/');
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend));

        $server->start();

        return new Response('');
    }
}
