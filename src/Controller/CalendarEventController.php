<?php

namespace App\Controller;

use App\Entity\CalendarEvent;
use App\Form\CalendarEventType;
use App\Repository\CalendarEventRepository;
use App\Repository\CalendarRepository;
use App\Service\SlugService;
use App\Voter\CalendarVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarEventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarEventRepository $calendarEventRepository,
        private CalendarRepository $calendarRepository,
        private SlugService $slugService,
    ) {
    }

    #[Route('/user/calendar/event/submit/{calendarId}', name: 'app_user_calendarevent_submit')]
    #[Route('/admin/calendar/event/submit/{calendarId}', name: 'app_admin_calendarevent_submit')]
    public function submit(int $calendarId, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageSlug = $request->query->get('pageSlug');

        $calendar = $this->calendarRepository->find($calendarId);
        if (!$calendar) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_list' : 'app_user_calendar_list');
        }

        $event = new CalendarEvent();
        $event->setCalendar($calendar);
        $event->setUser($this->getUser());

        $dateParam = $request->query->get('date');
        if ($dateParam) {
            $date = \DateTime::createFromFormat('Y-m-d', $dateParam);
            if ($date) {
                $event->setStartDate($date);
            } else {
                $event->setStartDate(new \DateTime());
            }
        } else {
            $event->setStartDate(new \DateTime());
        }

        $form = $this->createForm(CalendarEventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setSlug($this->slugService->generateUniqueSlug($event->getTitle(), 'CalendarEvent'));

            $startDate = $form->get('startDate')->getData();
            if ($startDate) {
                $event->setStartDate($startDate);
            }

            $endDate = $form->get('endDate')->getData();
            if ($endDate) {
                $event->setEndDate($endDate);
            }

            $this->em->persist($event);
            $this->em->flush();

            $this->addFlash('success', 'Événement créé avec succès');

            return $this->getRedirectResponse($request, $isAdmin, $calendar, $pageSlug);
        }

        return $this->render('calendar/event/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Nouvel événement',
            'form' => $form,
            'routecancel' => $isAdmin ? 'app_admin_calendar_view' : 'app_user_calendar_view',
            'calendar' => $calendar,
            'isAdmin' => $isAdmin,
            'pageSlug' => $pageSlug,
        ]);
    }

    #[Route('/user/calendar/event/update/{calendarId}/{id}', name: 'app_user_calendarevent_update')]
    #[Route('/admin/calendar/event/update/{calendarId}/{id}', name: 'app_admin_calendarevent_update')]
    public function update(int $calendarId, int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageSlug = $request->query->get('pageSlug');

        $event = $this->calendarEventRepository->find($id);
        if (!$event) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_list' : 'app_user_calendar_list');
        }

        if (!$this->isGranted(CalendarVoter::EVENT_EDIT, $event)) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_view' : 'app_user_calendar_view', ['slug' => $event->getCalendar()->getSlug()]);
        }

        $form = $this->createForm(CalendarEventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newSlug = $this->slugService->generateUniqueSlug($event->getTitle(), 'CalendarEvent', $event->getId());
            if ($newSlug !== $event->getSlug()) {
                $event->setSlug($newSlug);
            }
            $this->em->flush();

            $this->addFlash('success', 'Événement modifié avec succès');

            return $this->getRedirectResponse($request, $isAdmin, $event->getCalendar(), $pageSlug);
        }

        return $this->render('calendar/event/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Modifier l\'événement',
            'form' => $form,
            'routecancel' => $isAdmin ? 'app_admin_calendar_view' : 'app_user_calendar_view',
            'calendar' => $event->getCalendar(),
            'isAdmin' => $isAdmin,
            'pageSlug' => $pageSlug,
        ]);
    }

    #[Route('/user/calendar/event/delete/{calendarId}/{id}', name: 'app_user_calendarevent_delete')]
    #[Route('/admin/calendar/event/delete/{calendarId}/{id}', name: 'app_admin_calendarevent_delete')]
    public function delete(int $calendarId, int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageSlug = $request->query->get('pageSlug');

        $event = $this->calendarEventRepository->find($id);
        if (!$event) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_list' : 'app_user_calendar_list');
        }

        if (!$this->isGranted(CalendarVoter::EVENT_EDIT, $event)) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_view' : 'app_user_calendar_view', ['slug' => $event->getCalendar()->getSlug()]);
        }

        $calendarSlug = $event->getCalendar()->getSlug();
        $this->em->remove($event);
        $this->em->flush();

        $this->addFlash('success', 'Événement supprimé');

        return $this->getRedirectResponse($request, $isAdmin, $event->getCalendar(), $pageSlug);
    }

    #[Route('/user/calendar/event/view/{slug}', name: 'app_user_calendarevent_view')]
    #[Route('/admin/calendar/event/view/{slug}', name: 'app_admin_calendarevent_view')]
    public function view(string $slug, ?string $_route = null): Response
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $event = $this->calendarEventRepository->findOneBy(['slug' => $slug]);
        if (!$event) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        if (!$this->isGranted(CalendarVoter::VIEW, $event)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        return $this->render('calendar/event/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => $event->getTitle(),
            'event' => $event,
            'calendar' => $event->getCalendar(),
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user/calendar/event/move/{id}', name: 'app_user_calendarevent_move', methods: ['POST'])]
    #[Route('/admin/calendar/event/move/{id}', name: 'app_admin_calendarevent_move', methods: ['POST'])]
    public function move(int $id, Request $request): JsonResponse
    {
        $event = $this->calendarEventRepository->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Événement introuvable'], 404);
        }

        if (!$this->isGranted(CalendarVoter::EVENT_EDIT, $event)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!empty($data['start'])) {
            $event->setStartDate(new \DateTime($data['start']));
        }
        if (!empty($data['end'])) {
            $event->setEndDate(new \DateTime($data['end']));
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    private function getRedirectResponse(Request $request, bool $isAdmin, $calendar = null, ?string $pageSlug = null): Response
    {
        if ($pageSlug) {
            return $this->redirectToRoute('app_user_page_view', ['slug' => $pageSlug]);
        }

        if ($request->query->get('from') === 'calendars' || str_ends_with($request->headers->get('referer', ''), '/calendars')) {
            return $this->redirectToRoute('app_calendars_all');
        }

        if ($calendar) {
            return $this->redirectToRoute(
                $isAdmin ? 'app_admin_calendar_view' : 'app_user_calendar_view',
                ['slug' => $calendar->getSlug()]
            );
        }

        return $this->redirectToRoute($isAdmin ? 'app_admin_calendar_list' : 'app_user_calendar_list');
    }
}
