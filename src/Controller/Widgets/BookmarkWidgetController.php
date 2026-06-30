<?php

namespace App\Controller\Widgets;

use App\Repository\BookmarkRepository;
use App\Repository\ItemRepository;
use App\Repository\PageWidgetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookmarkWidgetController extends AbstractController
{
    #[Route('/user/widget/bookmark/{pageWidgetId}', name: 'app_user_pagewidget_bookmark')]
    #[Route('/admin/widget/bookmark/{pageWidgetId}', name: 'app_admin_pagewidget_bookmark')]
    public function __invoke(
        int $pageWidgetId,
        PageWidgetRepository $pageWidgetRepository,
        BookmarkRepository $bookmarkRepository,
        ItemRepository $itemRepository,
        ?string $_route,
    ): Response {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->getUser() !== null;
        $user = $this->getUser();

        $bookmarks = $user
            ? $bookmarkRepository->findBy(['user' => $user], ['title' => 'ASC'])
            : [];

        $favoritedItems = $user
            ? $itemRepository->createQueryBuilder('i')
                ->innerJoin('i.users', 'u', 'WITH', 'u = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult()
            : [];

        $allItems = array_merge($bookmarks, $favoritedItems);
        usort($allItems, fn($a, $b) => $a->getTitle() <=> $b->getTitle());

        // Add isItem flag for template
        $itemsWithFlags = array_map(function ($item) use ($favoritedItems) {
            $item->_isItem = in_array($item, $favoritedItems, true);
            return $item;
        }, $allItems);

        return $this->render('widget/bookmark.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'items' => $itemsWithFlags,
            'user' => $user,
        ]);
    }
}
