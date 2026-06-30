<?php

namespace App\Controller\Widgets;

use App\Repository\IconRepository;
use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LinkWidgetController extends AbstractController
{
    #[Route('/user/widget/link/{pageWidgetId}', name: 'app_user_pagewidget_link')]
    #[Route('/admin/widget/link/{pageWidgetId}', name: 'app_admin_pagewidget_link')]
    public function __invoke(
        int $pageWidgetId,
        PageWidgetRepository $pageWidgetRepository,
        IconRepository $iconRepository,
        ?string $_route,
    ): Response {
        $isAdmin = str_starts_with($_route ?? '', 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $content = $pageWidget->getContent() ?? [];
        $links = $content['links'] ?? [];

        $icons = $iconRepository->findBy([], ['route' => 'ASC']);
        foreach ($links as &$link) {
            $link['icon'] = null;
            if (!empty($link['iconId'])) {
                $link['icon'] = $iconRepository->find($link['iconId']);
            }
        }
        unset($link);

        // Sort links alphabetically by title
        usort($links, fn($a, $b) => ($a['title'] ?? '') <=> ($b['title'] ?? ''));

        return $this->render('widget/link.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'isAdmin' => $isAdmin,
            'links' => $links,
            'icons' => $icons,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_link_save' : 'app_user_pagewidget_link_save',
        ]);
    }

    #[Route('/user/widget/link/edit/{pageWidgetId}', name: 'app_user_pagewidget_link_edit')]
    #[Route('/admin/widget/link/edit/{pageWidgetId}', name: 'app_admin_pagewidget_link_edit')]
    public function edit(
        int $pageWidgetId,
        PageWidgetRepository $pageWidgetRepository,
        IconRepository $iconRepository,
        ?string $_route,
    ): Response {
        $isAdmin = str_starts_with($_route ?? '', 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_page_list' : 'app_user_page_list');
        }

        $content = $pageWidget->getContent() ?? [];
        $links = $content['links'] ?? [];

        $icons = $iconRepository->findBy([], ['route' => 'ASC']);

        $listRoute = $isAdmin ? 'app_admin_page_view' : 'app_user_page_view';
        $page = $pageWidget->getPage();

        return $this->render('widget/link_edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Éditer les liens du widget',
            'pageWidget' => $pageWidget,
            'page' => $page,
            'links' => $links,
            'icons' => $icons,
            'routecancel' => $listRoute,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_link_save' : 'app_user_pagewidget_link_save',
        ]);
    }

    #[Route('/user/widget/link/save/{pageWidgetId}', name: 'app_user_pagewidget_link_save')]
    #[Route('/admin/widget/link/save/{pageWidgetId}', name: 'app_admin_pagewidget_link_save')]
    public function save(
        int $pageWidgetId,
        Request $request,
        PageWidgetRepository $pageWidgetRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new JsonResponse(['error' => 'Widget introuvable'], 404);
        }

        if (!$this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $content = $pageWidget->getContent() ?? [];
        $content['links'] = $data['links'] ?? [];
        $pageWidget->setContent($content);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
