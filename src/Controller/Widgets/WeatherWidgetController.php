<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherWidgetController extends AbstractController
{
    private const API_BASE = 'https://api.open-meteo.com/v1/forecast';
    private const GEOCODING_API = 'https://geocoding-api.open-meteo.com/v1/search';

    private const WEATHER_CODES = [
        0 => ['label' => 'Ciel dégagé', 'icon' => 'fa-sun', 'color' => '#f59e0b'],
        1 => ['label' => 'Principalement dégagé', 'icon' => 'fa-cloud-sun', 'color' => '#fbbf24'],
        2 => ['label' => 'Partiellement nuageux', 'icon' => 'fa-cloud-sun', 'color' => '#94a3b8'],
        3 => ['label' => 'Couvert', 'icon' => 'fa-cloud', 'color' => '#94a3b8'],
        45 => ['label' => 'Brouillard', 'icon' => 'fa-smog', 'color' => '#9ca3af'],
        48 => ['label' => 'Brouillard givrant', 'icon' => 'fa-smog', 'color' => '#9ca3af'],
        51 => ['label' => 'Bruine légère', 'icon' => 'fa-cloud-rain', 'color' => '#60a5fa'],
        53 => ['label' => 'Bruine modérée', 'icon' => 'fa-cloud-rain', 'color' => '#60a5fa'],
        55 => ['label' => 'Bruine dense', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#3b82f6'],
        61 => ['label' => 'Pluie légère', 'icon' => 'fa-cloud-rain', 'color' => '#60a5fa'],
        63 => ['label' => 'Pluie modérée', 'icon' => 'fa-cloud-rain', 'color' => '#3b82f6'],
        65 => ['label' => 'Pluie forte', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2563eb'],
        71 => ['label' => 'Neige légère', 'icon' => 'fa-snowflake', 'color' => '#e2e8f0'],
        73 => ['label' => 'Neige modérée', 'icon' => 'fa-snowflake', 'color' => '#cbd5e1'],
        75 => ['label' => 'Neige forte', 'icon' => 'fa-snowflake', 'color' => '#94a3b8'],
        80 => ['label' => 'Averses légères', 'icon' => 'fa-cloud-sun-rain', 'color' => '#60a5fa'],
        81 => ['label' => 'Averses modérées', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#3b82f6'],
        82 => ['label' => 'Averses violentes', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2563eb'],
        85 => ['label' => 'Averses de neige', 'icon' => 'fa-snowflake', 'color' => '#cbd5e1'],
        86 => ['label' => 'Fortes averses de neige', 'icon' => 'fa-snowflake', 'color' => '#94a3b8'],
        95 => ['label' => 'Orage', 'icon' => 'fa-cloud-bolt', 'color' => '#a855f7'],
        96 => ['label' => 'Orage avec grêle légère', 'icon' => 'fa-cloud-bolt', 'color' => '#a855f7'],
        99 => ['label' => 'Orage avec grêle forte', 'icon' => 'fa-cloud-bolt', 'color' => '#7c3aed'],
    ];

    private const DAY_NAMES = [
        0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    #[Route('/user/widget/weather/{pageWidgetId}', name: 'app_user_pagewidget_weather')]
    #[Route('/admin/widget/weather/{pageWidgetId}', name: 'app_admin_pagewidget_weather')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $content = $pageWidget->getContent() ?? [];
        $latitude = $content['latitude'] ?? null;
        $longitude = $content['longitude'] ?? null;
        $city = $content['city'] ?? '';

        $weather = null;
        if ($latitude && $longitude) {
            $weather = $this->fetchWeather((float) $latitude, (float) $longitude);
        }

        return $this->render('widget/weather.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'isAdmin' => $isAdmin,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'weather' => $weather,
            'weatherCodes' => self::WEATHER_CODES,
            'dayNames' => self::DAY_NAMES,
        ]);
    }

    #[Route('/user/widget/weather/save/{pageWidgetId}', name: 'app_user_pagewidget_weather_save')]
    #[Route('/admin/widget/weather/save/{pageWidgetId}', name: 'app_admin_pagewidget_weather_save')]
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
        $content['city'] = $data['city'] ?? $content['city'] ?? '';
        $content['latitude'] = $data['latitude'] ?? $content['latitude'] ?? null;
        $content['longitude'] = $data['longitude'] ?? $content['longitude'] ?? null;
        $pageWidget->setContent($content);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true]);
    }

    #[Route('/widget/weather/geocode', name: 'app_widget_weather_geocode')]
    public function geocode(\Symfony\Component\HttpFoundation\Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $name = $request->query->get('name', '');
        if (strlen($name) < 2) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([]);
        }

        try {
            $response = $this->httpClient->request('GET', self::GEOCODING_API, [
                'query' => [
                    'name' => $name,
                    'count' => 5,
                    'language' => 'fr',
                ],
            ]);

            $data = json_decode($response->getContent(), true);

            return new \Symfony\Component\HttpFoundation\JsonResponse($data['results'] ?? []);
        } catch (\Exception $e) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([], 500);
        }
    }

    private function fetchWeather(float $lat, float $lon): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE, [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,wind_direction_10m,is_day',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'timezone' => 'auto',
                    'forecast_days' => 7,
                ],
            ]);

            return json_decode($response->getContent(), true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
