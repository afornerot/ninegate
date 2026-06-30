<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Bnine\FilesBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GalleryWidgetController extends AbstractController
{
    #[Route('/user/widget/gallery/{pageWidgetId}', name: 'app_user_pagewidget_gallery')]
    #[Route('/admin/widget/gallery/{pageWidgetId}', name: 'app_admin_pagewidget_gallery')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, FileService $fileService, ?string $_route): Response
    {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $domain = 'pagewidgetfile';
        $id = $pageWidgetId;

        $fileService->init($domain, (string) $id);

        return $this->render('widget/gallery.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'domain' => $domain,
            'id' => $id,
            'editable' => $canManage ? 1 : 0,
        ]);
    }
}
