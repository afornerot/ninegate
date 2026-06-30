<?php

namespace App\Controller;

use App\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private ImageService $imageService)
    {
    }

    #[Route('/user/upload/privateimage01/{endpoint}', name: 'app_user_upload_privateimage01')]
    public function privateimage01(string $endpoint, Request $request): Response
    {
        $reportThumb = $request->query->get('reportThumb');

        return $this->render('upload\imageprivate01.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'endpoint' => $endpoint,
            'reportThumb' => $reportThumb,
            'toReport' => false,
            'image' => '',
            'thumb' => '',
        ]);
    }

    #[Route('/user/upload/privateimage02/{endpoint}', name: 'app_user_upload_privateimage02')]
    public function privateimage02(string $endpoint, Request $request): Response
    {
        $reportThumb = $request->query->get('reportThumb');
        $path = $request->query->get('path');
        $file = $request->query->get('file');
        $image = $this->getParameter('kernel.project_dir').'/'.$path.'/'.$file;
        $thumb = $this->getParameter('kernel.project_dir').'/'.$path.'/thumb_'.$file;

        // Redimentionner
        $this->imageService->resizeImage($image, 1200, 1200);
        $toReport = true;

        return $this->render('upload\imageprivate02.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'endpoint' => $endpoint,
            'reportThumb' => $reportThumb,
            'toReport' => $toReport,
            'image' => $path.'/'.$file,
            'thumb' => $path.'/thumb_'.$file,
        ]);
    }

    #[Route('/user/upload/crop01/{endpoint}', name: 'app_user_upload_crop01')]
    public function crop01(string $endpoint, Request $request): Response
    {
        $reportThumb = $request->query->get('reportThumb');

        return $this->render('upload\crop01.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'endpoint' => $endpoint,
            'reportThumb' => $reportThumb,
        ]);
    }

    #[Route('/user/upload/crop02', name: 'app_user_upload_crop02')]
    public function crop02(Request $request): Response
    {
        $reportThumb = $request->query->get('reportThumb');
        $path = $request->query->get('path');
        $file = $request->query->get('file');
        $image = $this->getParameter('kernel.project_dir').'/public/'.$path.'/'.$file;
        $thumb = $this->getParameter('kernel.project_dir').'/public/'.$path.'/thumb_'.$file;

        // Redimentionner
        $this->imageService->resizeImage($image, 700, 700);

        // Construction du formulaire
        $form = $this->createFormBuilder()
            ->add('submit', SubmitType::class, ['label' => 'Valider', 'attr' => ['class' => 'btn btn-success']])
            ->add('x1', HiddenType::class)
            ->add('y1', HiddenType::class)
            ->add('x2', HiddenType::class)
            ->add('y2', HiddenType::class)
            ->add('w', HiddenType::class)
            ->add('h', HiddenType::class)
            ->getForm();

        // Récupération des data du formulaire
        $form->handleRequest($request);
        $toReport = false;
        // Sur validation on généère la miniature croppée
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $toReport = true;
            $this->imageService->cropImage($image, $thumb, $data['x1'], $data['y1'], $data['w'], $data['h'], 150, 150);
        }

        return $this->render('upload\crop02.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'reportThumb' => $reportThumb,
            'image' => $path.'/'.$file,
            'thumb' => $path.'/thumb_'.$file,
            'form' => $form,
            'toReport' => $toReport,
        ]);
    }
}
