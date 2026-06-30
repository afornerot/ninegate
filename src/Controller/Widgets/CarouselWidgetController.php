<?php

namespace App\Controller\Widgets;

use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Bnine\FilesBundle\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CarouselWidgetController extends AbstractController
{
    #[Route('/user/widget/carousel/{pageWidgetId}', name: 'app_user_pagewidget_carousel')]
    #[Route('/admin/widget/carousel/{pageWidgetId}', name: 'app_admin_pagewidget_carousel')]
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

        $content = $pageWidget->getContent() ?? [];
        $slides = $content['slides'] ?? [];

        return $this->render('widget/carousel.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'domain' => $domain,
            'id' => $id,
            'editable' => $canManage ? 1 : 0,
            'slides' => $slides,
            'routeSave' => $isAdmin ? 'app_admin_pagewidget_carousel_save' : 'app_user_pagewidget_carousel_save',
        ]);
    }

    #[Route('/user/widget/carousel/save/{pageWidgetId}', name: 'app_user_pagewidget_carousel_save')]
    #[Route('/admin/widget/carousel/save/{pageWidgetId}', name: 'app_admin_pagewidget_carousel_save')]
    public function save(int $pageWidgetId, Request $request, PageWidgetRepository $pageWidgetRepository, EntityManagerInterface $em, FileService $fileService, ?string $_route): JsonResponse
    {
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new JsonResponse(['error' => 'Widget introuvable'], 404);
        }

        if (!$this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $newSlides = $data['slides'] ?? [];

        $content = $pageWidget->getContent() ?? [];
        $oldSlides = $content['slides'] ?? [];

        // Find images that were removed
        $oldImages = array_column($oldSlides, 'image');
        $newImages = array_column($newSlides, 'image');
        $removedImages = array_diff($oldImages, $newImages);

        // Delete physical files for removed images
        $domain = 'pagewidgetfile';
        $id = (string) $pageWidgetId;
        foreach ($removedImages as $image) {
            try {
                $fileService->delete($domain, $id, $image);
            } catch (\Exception $e) {
                // File may already be deleted, ignore
            }
        }

        $content['slides'] = $newSlides;
        $pageWidget->setContent($content);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
