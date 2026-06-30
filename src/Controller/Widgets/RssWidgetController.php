<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RssWidgetController extends AbstractController
{
    #[Route('/user/widget/rss/{pageWidgetId}', name: 'app_user_pagewidget_rss')]
    #[Route('/admin/widget/rss/{pageWidgetId}', name: 'app_admin_pagewidget_rss')]
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

        $feedUrls = $content['feedUrls'] ?? $config['feedUrls']['default'] ?? '';
        $maxItems = $content['maxItems'] ?? $config['maxItems']['default'] ?? 10;
        $showDescription = $content['showDescription'] ?? $config['showDescription']['default'] ?? true;

        $feedData = $this->fetchAllFeeds($feedUrls, (int) $maxItems, $pageWidget->getId());
        $items = $feedData['items'];
        $feeds = $feedData['feeds'];

        return $this->render('widget/rss.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'isAdmin' => $isAdmin,
            'feedUrls' => $feedUrls,
            'maxItems' => $maxItems,
            'showDescription' => $showDescription,
            'items' => $items,
            'feeds' => $feeds,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_rss_save' : 'app_user_pagewidget_rss_save',
        ]);
    }

    #[Route('/user/widget/rss/save/{pageWidgetId}', name: 'app_user_pagewidget_rss_save')]
    #[Route('/admin/widget/rss/save/{pageWidgetId}', name: 'app_admin_pagewidget_rss_save')]
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
        $content['feedUrls'] = $data['feedUrls'] ?? $content['feedUrls'] ?? '';
        $content['maxItems'] = $data['maxItems'] ?? $content['maxItems'] ?? 10;
        $content['showDescription'] = $data['showDescription'] ?? $content['showDescription'] ?? true;
        $pageWidget->setContent($content);
        $em->flush();

        $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('rss', 3600, $this->getParameter('kernel.cache_dir'));
        $cache->delete('feeds_' . $pageWidgetId . '_' . md5(serialize(array_filter(array_map('trim', explode("\n", $content['feedUrls'] ?? ''))))));

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true]);
    }

    private function fetchAllFeeds(string $feedUrls, int $maxItems, int $pageWidgetId): array
    {
        if (empty($feedUrls)) {
            return ['items' => [], 'feeds' => []];
        }

        $urls = array_filter(array_map('trim', explode("\n", $feedUrls)));
        if (empty($urls)) {
            return ['items' => [], 'feeds' => []];
        }

        $cache = new FilesystemAdapter('rss', 3600, $this->getParameter('kernel.cache_dir'));
        $cacheKey = 'feeds_' . $pageWidgetId . '_' . md5(serialize($urls));

        $cached = $cache->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        $allItems = [];
        $feedNames = [];
        foreach ($urls as $url) {
            $feed = $this->fetchFeed($url, $maxItems);
            $title = $feed['title'] ?: parse_url($url, PHP_URL_HOST) ?? $url;
            $feedNames[] = $title;
            foreach ($feed['items'] as &$item) {
                $item['source'] = $title;
            }
            unset($item);
            $allItems = array_merge($allItems, $feed['items']);
        }

        usort($allItems, fn($a, $b) => strtotime($b['pubDate'] ?? '0') - strtotime($a['pubDate'] ?? '0'));

        $cached->set(['items' => $allItems, 'feeds' => $feedNames]);
        $cache->save($cached);

        return ['items' => $allItems, 'feeds' => $feedNames];
    }

    private function fetchFeed(string $url, int $maxItems): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "Accept: application/rss+xml, application/atom+xml, application/xml, text/xml\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $xml = @file_get_contents($url, false, $context);
            if ($xml === false) {
                return ['title' => '', 'items' => []];
            }

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            libxml_use_internal_errors(true);
            $dom->loadXML($xml);
            libxml_clear_errors();

            $result = ['title' => '', 'items' => []];

            // RSS 2.0
            $channel = $dom->getElementsByTagName('channel')->item(0);
            if ($channel) {
                $result['title'] = $channel->getElementsByTagName('title')->item(0)?->textContent ?? '';
                $rssItems = $dom->getElementsByTagName('item');
                for ($i = 0; $i < min($rssItems->count(), $maxItems); $i++) {
                    $item = $rssItems->item($i);
                    $result['items'][] = [
                        'title' => $item->getElementsByTagName('title')->item(0)?->textContent ?? '',
                        'link' => $item->getElementsByTagName('link')->item(0)?->textContent ?? '',
                        'description' => $item->getElementsByTagName('description')->item(0)?->textContent ?? '',
                        'pubDate' => $item->getElementsByTagName('pubDate')->item(0)?->textContent ?? '',
                        'image' => $this->extractImage($item),
                    ];
                }
                return $result;
            }

            // Atom
            $feedTag = $dom->getElementsByTagName('feed')->item(0);
            if ($feedTag) {
                $result['title'] = $feedTag->getElementsByTagName('title')->item(0)?->textContent ?? '';
                $entries = $dom->getElementsByTagName('entry');
                for ($i = 0; $i < min($entries->count(), $maxItems); $i++) {
                    $entry = $entries->item($i);
                    $link = '';
                    foreach ($entry->getElementsByTagName('link') as $l) {
                        if ($l->getAttribute('rel') === 'alternate') {
                            $link = $l->getAttribute('href');
                            break;
                        }
                    }
                    $result['items'][] = [
                        'title' => $entry->getElementsByTagName('title')->item(0)?->textContent ?? '',
                        'link' => $link,
                        'description' => $entry->getElementsByTagName('summary')->item(0)?->textContent ?? $entry->getElementsByTagName('content')->item(0)?->textContent ?? '',
                        'pubDate' => $entry->getElementsByTagName('published')->item(0)?->textContent ?? $entry->getElementsByTagName('updated')->item(0)?->textContent ?? '',
                        'image' => $this->extractImage($entry),
                    ];
                }
                return $result;
            }

            return ['title' => '', 'items' => []];
        } catch (\Exception $e) {
            return ['title' => '', 'items' => []];
        }
    }

    private function extractImage(\DOMElement $node): ?string
    {
        $enclosures = $node->getElementsByTagName('enclosure');
        foreach ($enclosures as $enclosure) {
            $rel = $enclosure->getAttribute('rel');
            $type = $enclosure->getAttribute('type');
            $url = $enclosure->getAttribute('url');
            if (($rel === 'thumb' || str_starts_with($type, 'image/')) && $url) {
                return $url;
            }
        }

        $description = $node->getElementsByTagName('description')->item(0)?->textContent ?? '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $m)) {
            return $m[1];
        }

        return null;
    }
}
