<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClockWidgetController extends AbstractController
{
    private const TIMEZONES = [
        'Europe/Paris' => 'Paris',
        'Europe/London' => 'Londres',
        'Europe/Berlin' => 'Berlin',
        'Europe/Madrid' => 'Madrid',
        'Europe/Rome' => 'Rome',
        'Europe/Amsterdam' => 'Amsterdam',
        'Europe/Brussels' => 'Bruxelles',
        'Europe/Zurich' => 'Zurich',
        'Europe/Vienna' => 'Vienne',
        'Europe/Warsaw' => 'Varsovie',
        'Europe/Prague' => 'Prague',
        'Europe/Bucharest' => 'Bucarest',
        'Europe/Athens' => 'Athènes',
        'Europe/Istanbul' => 'Istanbul',
        'America/New_York' => 'New York',
        'America/Chicago' => 'Chicago',
        'America/Denver' => 'Denver',
        'America/Los_Angeles' => 'Los Angeles',
        'America/Toronto' => 'Toronto',
        'America/Montreal' => 'Montréal',
        'America/Sao_Paulo' => 'São Paulo',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires',
        'Asia/Tokyo' => 'Tokyo',
        'Asia/Shanghai' => 'Shanghai',
        'Asia/Kolkata' => 'Mumbai',
        'Asia/Dubai' => 'Dubaï',
        'Asia/Singapore' => 'Singapour',
        'Asia/Seoul' => 'Séoul',
        'Australia/Sydney' => 'Sydney',
        'Australia/Melbourne' => 'Melbourne',
        'Pacific/Auckland' => 'Auckland',
        'Africa/Cairo' => 'Le Caire',
        'Africa/Johannesburg' => 'Johannesbourg',
    ];

    #[Route('/user/widget/clock/{pageWidgetId}', name: 'app_user_pagewidget_clock')]
    #[Route('/admin/widget/clock/{pageWidgetId}', name: 'app_admin_pagewidget_clock')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $content = $pageWidget->getContent() ?? [];
        $config = $pageWidget->getWidget()->getConfig() ?? [];

        $mainTimezone = $content['timezone'] ?? $config['timezone']['default'] ?? 'Europe/Paris';
        $extraTimezonesRaw = $content['extraTimezones'] ?? $config['extraTimezones']['default'] ?? '';
        $extraTimezones = array_filter(array_map('trim', explode(',', $extraTimezonesRaw)));

        return $this->render('widget/clock.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'isAdmin' => $isAdmin,
            'mainTimezone' => $mainTimezone,
            'extraTimezones' => $extraTimezones,
            'timezones' => self::TIMEZONES,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_clock_save' : 'app_user_pagewidget_clock_save',
        ]);
    }

    #[Route('/user/widget/clock/save/{pageWidgetId}', name: 'app_user_pagewidget_clock_save')]
    #[Route('/admin/widget/clock/save/{pageWidgetId}', name: 'app_admin_pagewidget_clock_save')]
    public function save(int $pageWidgetId, \Symfony\Component\HttpFoundation\Request $request, PageWidgetRepository $pageWidgetRepository, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Widget introuvable'], 404);
        }

        if (!$this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $content = $pageWidget->getContent() ?? [];
        $content['timezone'] = $data['timezone'] ?? $content['timezone'] ?? 'Europe/Paris';
        $content['extraTimezones'] = $data['extraTimezones'] ?? $content['extraTimezones'] ?? '';
        $pageWidget->setContent($content);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true]);
    }
}
