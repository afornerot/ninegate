<?php

namespace App\Controller;

use App\Repository\PageTemplateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PageTemplateController extends AbstractController
{
    public function __construct(
        private PageTemplateRepository $pageTemplateRepository,
    ) {
    }

    #[Route('/pagetemplate', name: 'app_page_templates_api')]
    public function templates(): JsonResponse
    {
        $templates = $this->pageTemplateRepository->findAll();
        $data = [];
        foreach ($templates as $t) {
            $data[] = [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'template' => $t->getTemplate(),
            ];
        }

        return new JsonResponse($data);
    }
}
