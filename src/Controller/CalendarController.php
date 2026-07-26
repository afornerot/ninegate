<?php

namespace App\Controller;

use App\Entity\Calendar;
use App\Form\CalendarType;
use App\Repository\CalendarEventRepository;
use App\Repository\CalendarRepository;
use App\Service\SlugService;
use App\Voter\CalendarVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
    private const CALENDAR_PREFIX = '/calendar';
    private const ROUTE_PREFIX_USER = 'app_user_calendar';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_calendar';

    public function __construct(
        private EntityManagerInterface $em,
        private CalendarRepository $calendarRepository,
        private CalendarEventRepository $calendarEventRepository,
        private SlugService $slugService,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user/calendar', name: 'app_user_calendar_list')]
    #[Route('/admin/calendar', name: 'app_admin_calendar_list')]
    public function list(?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $user = $this->getUser();

        if ($isAdmin) {
            $calendars = $this->calendarRepository->findBy([], ['calendarOrder' => 'ASC']);
        } else {
            $calendars = $this->calendarRepository->findAccessibleCalendars($user);
        }

        return $this->render('calendar/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $isAdmin ? 'Calendriers' : 'Mes Calendriers',
            'calendars' => $calendars,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user/calendar/submit', name: 'app_user_calendar_submit')]
    #[Route('/admin/calendar/submit', name: 'app_admin_calendar_submit')]
    public function submit(Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);
        $user = $this->getUser();

        $calendar = new Calendar();
        $form = $this->createForm(CalendarType::class, $calendar, [
            'isAdmin' => $isAdmin,
            'user' => $user,
            'mode' => 'submit',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $calendar->setSlug($this->slugService->generateUniqueSlug($calendar->getTitle(), 'Calendar'));

            $calendarType = $form->get('calendarType')->getData();
            if ($isAdmin) {
                if ($calendarType === 'personal') {
                    $calendarUser = $form->get('user')->getData();
                    $calendar->setUser($calendarUser ?: $user);
                    $calendar->getGroups()->clear();
                    $calendar->setRoles(null);
                }
            } else {
                if ($calendarType === 'personal') {
                    $calendar->setUser($user);
                    $calendar->getGroups()->clear();
                    $calendar->setRoles(null);
                }
            }

            if ($isAdmin && $form->has('allUsers') && $form->get('allUsers')->getData()) {
                $calendar->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $calendar->getGroups()->clear();
                $calendar->setUser(null);
            }

            $this->em->persist($calendar);
            $this->em->flush();

            $this->addFlash('success', 'Calendrier créé avec succès');

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('calendar/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Calendrier',
            'form' => $form,
            'routecancel' => $listRoute,
            'calendar' => $calendar,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user/calendar/update/{id}', name: 'app_user_calendar_update')]
    #[Route('/admin/calendar/update/{id}', name: 'app_admin_calendar_update')]
    public function update(int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);
        $user = $this->getUser();

        $calendar = $this->calendarRepository->find($id);
        if (!$calendar) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(CalendarVoter::EDIT, $calendar)) {
            return $this->redirectToRoute($listRoute);
        }

        $form = $this->createForm(CalendarType::class, $calendar, [
            'isAdmin' => $isAdmin,
            'user' => $user,
            'mode' => 'update',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newSlug = $this->slugService->generateUniqueSlug($calendar->getTitle(), 'Calendar', $calendar->getId());
            if ($newSlug !== $calendar->getSlug()) {
                $calendar->setSlug($newSlug);
            }
            $this->em->flush();

            $this->addFlash('success', 'Calendrier modifié avec succès');

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('calendar/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Calendrier',
            'form' => $form,
            'routecancel' => $listRoute,
            'calendar' => $calendar,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user/calendar/delete/{id}', name: 'app_user_calendar_delete')]
    #[Route('/admin/calendar/delete/{id}', name: 'app_admin_calendar_delete')]
    public function delete(int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $calendar = $this->calendarRepository->find($id);
        if (!$calendar) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(CalendarVoter::DELETE, $calendar)) {
            return $this->redirectToRoute($listRoute);
        }

        try {
            $this->em->remove($calendar);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute($listRoute);
    }

    #[Route('/user/calendar/view/{slug}', name: 'app_user_calendar_view')]
    #[Route('/admin/calendar/view/{slug}', name: 'app_admin_calendar_view')]
    public function view(string $slug, ?string $_route = null): Response
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $calendar = $this->calendarRepository->findOneBy(['slug' => $slug]);
        if (!$calendar) {
            throw $this->createNotFoundException('Calendrier non trouvé');
        }

        if (!$this->isGranted(CalendarVoter::VIEW, $calendar)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        return $this->render('calendar/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => $calendar->getTitle(),
            'calendar' => $calendar,
            'isAdmin' => $isAdmin,
            'routeevent_submit' => $isAdmin ? 'app_admin_calendarevent_submit' : 'app_user_calendarevent_submit',
        ]);
    }

    #[Route('/user/calendar/events/{slug}', name: 'app_user_calendar_events')]
    #[Route('/admin/calendar/events/{slug}', name: 'app_admin_calendar_events')]
    public function events(string $slug, ?string $_route = null): JsonResponse
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $calendar = $this->calendarRepository->findOneBy(['slug' => $slug]);
        if (!$calendar) {
            return new JsonResponse([]);
        }

        $user = $this->getUser();
        if (!$this->calendarRepository->isCalendarAccessibleForUser($calendar, $user)) {
            return new JsonResponse([], 403);
        }

        $events = $this->calendarEventRepository->findBy(['calendar' => $calendar], ['startDate' => 'ASC']);

        $data = [];
        foreach ($events as $event) {
            $item = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'allDay' => $event->isAllDay(),
                'color' => $event->getColor() ?: $calendar->getColor(),
                'url' => $this->generateUrl(
                    $isAdmin ? 'app_admin_calendarevent_view' : 'app_user_calendarevent_view',
                    ['slug' => $event->getSlug()]
                ),
            ];

            if ($event->isAllDay()) {
                $item['start'] = $event->getStartDate()->format('Y-m-d');
                $item['end'] = $event->getEndDate()
                    ? $event->getEndDate()->modify('+1 day')->format('Y-m-d')
                    : $event->getStartDate()->modify('+1 day')->format('Y-m-d');
            } else {
                $item['start'] = $event->getStartDate()->format('Y-m-d\TH:i:s');
                $item['end'] = $event->getEndDate()
                    ? $event->getEndDate()->format('Y-m-d\TH:i:s')
                    : null;
            }

            $data[] = $item;
        }

        return new JsonResponse($data);
    }

    #[Route('/calendars', name: 'app_calendars_all')]
    public function all(): Response
    {
        $user = $this->getUser();
        $calendars = $this->calendarRepository->findAccessibleCalendars($user);

        return $this->render('calendar/all.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Tous les calendriers',
            'calendars' => $calendars,
        ]);
    }

    #[Route('/calendars/events.json', name: 'app_calendars_events_json')]
    public function allEventsJson(): JsonResponse
    {
        $user = $this->getUser();
        $calendars = $this->calendarRepository->findAccessibleCalendars($user);

        $events = [];
        foreach ($calendars as $calendar) {
            foreach ($calendar->getEvents() as $event) {
                $item = [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'allDay' => $event->isAllDay(),
                    'color' => $event->getColor() ?: $calendar->getColor(),
                    'url' => $this->generateUrl('app_user_calendarevent_view', ['slug' => $event->getSlug(), 'from' => 'calendars']),
                ];

                if ($event->isAllDay()) {
                    $item['start'] = $event->getStartDate()->format('Y-m-d');
                    $item['end'] = $event->getEndDate()
                        ? $event->getEndDate()->modify('+1 day')->format('Y-m-d')
                        : $event->getStartDate()->modify('+1 day')->format('Y-m-d');
                } else {
                    $item['start'] = $event->getStartDate()->format('Y-m-d\TH:i:s');
                    $item['end'] = $event->getEndDate()
                        ? $event->getEndDate()->format('Y-m-d\TH:i:s')
                        : null;
                }

                $events[] = $item;
            }
        }

        return new JsonResponse($events);
    }

    private function isAdmin(): bool
    {
        $route = $this->getRequest()->attributes->get('_route') ?? '';
        return str_starts_with($route, 'app_admin');
    }
}
