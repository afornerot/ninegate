<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\AnnonceHidden;
use App\Form\AnnonceType;
use App\Repository\AnnonceCategoryRepository;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnonceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AnnonceRepository $annonceRepository,
        private AnnonceCategoryRepository $annonceCategoryRepository,
    ) {
    }

    #[Route('/admin/annonce/submit/{categoryId}', name: 'app_admin_annonce_submit')]
    public function submit(int $categoryId, Request $request): Response
    {
        $category = $this->annonceCategoryRepository->find($categoryId);
        if (!$category) {
            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        $annonce = new Annonce();
        $annonce->setCategory($category);

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('allUsers') && $form->get('allUsers')->getData()) {
                $annonce->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $annonce->getGroups()->clear();
            }

            $this->em->persist($annonce);
            $this->em->flush();

            $this->addFlash('success', 'Annonce créée avec succès');

            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        return $this->render('annonce/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvelle annonce',
            'form' => $form,
            'categoryId' => $categoryId,
        ]);
    }

    #[Route('/admin/annonce/update/{categoryId}/{id}', name: 'app_admin_annonce_update')]
    public function update(int $categoryId, int $id, Request $request): Response
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce) {
            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('allUsers') && $form->get('allUsers')->getData()) {
                $annonce->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $annonce->getGroups()->clear();
            }

            $this->em->flush();

            $this->addFlash('success', 'Annonce modifiée avec succès');

            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        return $this->render('annonce/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier l\'annonce',
            'form' => $form,
            'categoryId' => $categoryId,
        ]);
    }

    #[Route('/admin/annonce/delete/{categoryId}/{id}', name: 'app_admin_annonce_delete')]
    public function delete(int $categoryId, int $id): Response
    {
        $annonce = $this->annonceRepository->find($id);
        if ($annonce) {
            $this->em->remove($annonce);
            $this->em->flush();
            $this->addFlash('success', 'Annonce supprimée');
        }

        return $this->redirectToRoute('app_admin_annoncecategory_list');
    }

    #[Route('/admin/annonce/move/{id}', name: 'app_admin_annonce_move', methods: ['POST'])]
    public function move(int $id, Request $request): JsonResponse
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce) {
            return new JsonResponse(['success' => false, 'error' => 'Annonce introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newCategoryId = $data['categoryId'] ?? null;
        $newOrder = $data['order'] ?? null;

        if ($newCategoryId !== null) {
            $newCategory = $this->annonceCategoryRepository->find($newCategoryId);
            if ($newCategory) {
                $annonce->setCategory($newCategory);
            }
        }

        if ($newOrder !== null) {
            $annonce->setSortOrder($newOrder);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/admin/annonce/reset-hidden/{id}', name: 'app_admin_annonce_reset_hidden')]
    public function resetHidden(int $id): Response
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce) {
            return $this->redirectToRoute('app_admin_annoncecategory_list');
        }

        $hiddenRepo = $this->em->getRepository(AnnonceHidden::class);
        $hiddens = $hiddenRepo->findBy(['annonce' => $annonce]);
        foreach ($hiddens as $hidden) {
            $this->em->remove($hidden);
        }
        $this->em->flush();

        $this->addFlash('success', 'Les masquages ont été réinitialisés pour cette annonce');

        return $this->redirectToRoute('app_admin_annoncecategory_list');
    }
}
