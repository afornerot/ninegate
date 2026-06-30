<?php

namespace App\Controller\Widgets;

use App\Entity\PageWidget;
use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NoteWidgetController extends AbstractController
{
    #[Route('/user/widget/note/{pageWidgetId}', name: 'app_user_pagewidget_note')]
    #[Route('/admin/widget/note/{pageWidgetId}', name: 'app_admin_pagewidget_note')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        return $this->render('widget/note.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_note_save' : 'app_user_pagewidget_note_save',
        ]);
    }

    #[Route('/user/widget/note/save/{pageWidgetId}', name: 'app_user_pagewidget_note_save')]
    #[Route('/admin/widget/note/save/{pageWidgetId}', name: 'app_admin_pagewidget_note_save')]
    public function save(int $pageWidgetId, Request $request, PageWidgetRepository $pageWidgetRepository, EntityManagerInterface $em, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new JsonResponse(['error' => 'Widget introuvable'], 404);
        }

        if (!$this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $markdown = $data['content'] ?? null;

        $content = $pageWidget->getContent() ?? [];
        $content['markdown'] = $markdown;
        $pageWidget->setContent($content);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
