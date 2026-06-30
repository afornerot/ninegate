<?php

namespace App\Controller\Widgets;

use App\Repository\ItemCategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\PageWidgetRepository;
use App\Service\WidgetConfigService;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BureauWidgetController extends AbstractController
{
    #[Route('/user/widget/bureau/{pageWidgetId}', name: 'app_user_pagewidget_bureau')]
    #[Route('/admin/widget/bureau/{pageWidgetId}', name: 'app_admin_pagewidget_bureau')]
    public function __invoke(
        int $pageWidgetId,
        PageWidgetRepository $pageWidgetRepository,
        ItemRepository $itemRepository,
        ItemCategoryRepository $itemCategoryRepository,
        WidgetConfigService $widgetConfigService,
        ?string $_route,
    ): Response {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);
        $user = $this->getUser();

        // Merge widget defaults with instance config using generic service
        $configFields = $pageWidget->getWidget()?->getConfig() ?? [];
        $widgetDefaults = $widgetConfigService->getDefaults($configFields);
        $instanceConfig = $pageWidget->getContent() ?? [];
        $config = array_merge($widgetDefaults, $instanceConfig);

        // Build query for accessible items (by group membership)
        $qb = $itemRepository->createQueryBuilder('i')
            ->leftJoin('i.groups', 'g')
            ->leftJoin('g.userGroups', 'ug', 'WITH', 'ug.user = :user')
            ->where('ug.user IS NOT NULL')
            ->setParameter('user', $user)
            ->distinct();

        // Filter by category if specified
        if (!empty($config['categoryId'])) {
            $category = $itemCategoryRepository->find($config['categoryId']);
            if ($category) {
                $qb->andWhere('i.category = :cat')->setParameter('cat', $category);
            }
        }

        $groupItems = $qb->orderBy('i.sortOrder', 'ASC')->getQuery()->getResult();

        // Also get items accessible by role (JSON field, filtered in PHP)
        $allItemsQb = $itemRepository->createQueryBuilder('i')
            ->orderBy('i.sortOrder', 'ASC');

        // Filter by category if specified
        if (!empty($config['categoryId'])) {
            $category = $itemCategoryRepository->find($config['categoryId']);
            if ($category) {
                $allItemsQb->andWhere('i.category = :cat')->setParameter('cat', $category);
            }
        }

        $allItems = $allItemsQb->getQuery()->getResult();

        $userRoles = $user->getRoles();
        $roleItems = array_filter($allItems, function ($item) use ($userRoles) {
            $itemRoles = $item->getRoles() ?? [];
            // "Tout le monde" : item accessible à tous si ROLE_VISITOR dans ses rôles
            if (in_array('ROLE_VISITOR', $itemRoles)) {
                return true;
            }
            return !empty(array_intersect($userRoles, $itemRoles));
        });

        // Merge and deduplicate
        $itemsById = [];
        foreach (array_merge($groupItems, $roleItems) as $item) {
            $itemsById[$item->getId()] = $item;
        }
        $items = array_values($itemsById);

        // Sort by order
        usort($items, fn($a, $b) => $a->getSortOrder() <=> $b->getSortOrder());

        // Separate favorites for virtual category
        $favoriteItems = [];
        if (!empty($config['showFavorites']) && $user) {
            $favoriteIds = array_map(fn($i) => $i->getId(), $user->getItems()->toArray());
            $favoriteItems = array_filter($items, fn($item) => in_array($item->getId(), $favoriteIds));
        }

        // Get categories for navbar
        $categories = $itemCategoryRepository->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('widget/bureau.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'items' => $items,
            'favoriteItems' => $favoriteItems,
            'config' => $config,
            'categories' => $categories,
            'user' => $user,
            'favoriteIds' => $user ? array_map(fn($i) => $i->getId(), $user->getItems()->toArray()) : [],
        ]);
    }

    #[Route('/user/widget/bureau/favorite/{itemId}', name: 'app_pagewidget_bureau_favorite', methods: ['POST'])]
    public function toggleFavorite(int $itemId, ItemRepository $itemRepository, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $item = $itemRepository->find($itemId);
        if (!$item) {
            return new JsonResponse(['error' => 'Item introuvable'], 404);
        }

        if ($item->getUsers()->contains($user)) {
            $item->removeUser($user);
            $isFav = false;
        } else {
            $item->addUser($user);
            $isFav = true;
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'isFavorite' => $isFav]);
    }
}
