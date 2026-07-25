<?php

namespace App\Controller;

use App\Entity\AnnonceCategory;
use App\Form\AnnonceCategoryType;
use App\Repository\AnnonceCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnonceCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AnnonceCategoryRepository $annonceCategoryRepository,
    ) {
    }

    #[Route('/admin/annoncecategory', name: 'app_admin_annoncecategory_list')]
    public function list(): Response
    {
        $categories = $this->annonceCategoryRepository->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('annoncecategory/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Annonces',
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/annoncecategory/submit', name: 'app_admin_annoncecategory_submit')]
    public function submit(Request $request): Response
    {
        $category = new AnnonceCategory();
        $form = $this->createForm(AnnonceCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Catégorie créée avec succès');

            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        return $this->render('annoncecategory/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvelle catégorie',
            'form' => $form,
        ]);
    }

    #[Route('/admin/annoncecategory/update/{id}', name: 'app_admin_annoncecategory_update')]
    public function update(int $id, Request $request): Response
    {
        $category = $this->annonceCategoryRepository->find($id);
        if (!$category) {
            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        $form = $this->createForm(AnnonceCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès');

            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        return $this->render('annoncecategory/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier la catégorie',
            'form' => $form,
        ]);
    }

    #[Route('/admin/annoncecategory/delete/{id}', name: 'app_admin_annoncecategory_delete')]
    public function delete(int $id): Response
    {
        $category = $this->annonceCategoryRepository->find($id);
        if ($category) {
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie supprimée');
        }

        return $this->redirectToRoute('app_admin_annoncecategory_list');
    }

    #[Route('/admin/annoncecategory/reorder', name: 'app_admin_annoncecategory_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        foreach ($order as $index => $id) {
            $category = $this->annonceCategoryRepository->find($id);
            if ($category) {
                $category->setSortOrder($index);
            }
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}
