<?php

namespace App\Controller\Widgets;

use App\Repository\CalendarEventRepository;
use App\Repository\CalendarRepository;
use App\Repository\PageWidgetRepository;
use App\Service\WidgetConfigService;
use App\Voter\WidgetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarWidgetController extends AbstractController
{
    public function __construct(
        private CalendarRepository $calendarRepository,
        private CalendarEventRepository $calendarEventRepository,
        private WidgetConfigService $widgetConfigService,
    ) {
    }

    #[Route('/user/widget/calendar/{pageWidgetId}', name: 'app_user_pagewidget_calendar')]
    #[Route('/admin/widget/calendar/{pageWidgetId}', name: 'app_admin_pagewidget_calendar')]
    public function __invoke(string $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageWidget = $pageWidgetRepository->find((int) $pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);
        $user = $this->getUser();

        $configFields = $pageWidget->getWidget()->getConfig() ?? [];
        $instanceConfig = $pageWidget->getContent() ?? [];
        $widgetDefaults = $this->widgetConfigService->getDefaults($configFields);
        $config = array_merge($widgetDefaults, $instanceConfig);

        $mode = $config['mode'] ?? 'user';
        $nbEvents = $config['nbEvents'] ?? $configFields['nbEvents']['default'] ?? 10;

        if ($mode === 'linked') {
            $page = $pageWidget->getPage();
            $pageGroups = $page->getGroups()->toArray();
            if (!empty($pageGroups)) {
                $calendars = $this->calendarRepository->findCalendarsByGroups($pageGroups);
                $events = $this->calendarEventRepository->findEventsByCalendars($calendars, $user, $nbEvents);
            } else {
                $events = [];
            }
        } else {
            $calendars = $this->calendarRepository->findAccessibleCalendars($user);
            $events = $this->calendarEventRepository->findEventsByCalendars($calendars, $user, $nbEvents);
        }

        usort($events, fn($a, $b) => $b->getStartDate() <=> $a->getStartDate());

        return $this->render('widget/calendar.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'events' => $events,
            'isAdmin' => $isAdmin,
        ]);
    }
}
