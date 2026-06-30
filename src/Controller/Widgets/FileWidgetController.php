<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Bnine\FilesBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FileWidgetController extends AbstractController
{
    #[Route('/user/widget/file/{pageWidgetId}', name: 'app_user_pagewidget_file')]
    #[Route('/admin/widget/file/{pageWidgetId}', name: 'app_admin_pagewidget_file')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, FileService $fileService, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route ?? '', 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $domain = 'pagewidgetfile';
        $id = $pageWidgetId;

        $fileService->init($domain, (string) $id);

        return $this->render('widget/file.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'domain' => $domain,
            'id' => $id,
            'editable' => $canManage ? 1 : 0,
        ]);
    }
}
