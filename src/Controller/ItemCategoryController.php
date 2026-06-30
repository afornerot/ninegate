<?php

namespace App\Controller;

use App\Entity\ItemCategory;
use App\Form\ItemCategoryType;
use App\Repository\ItemCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ItemCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemCategoryRepository $itemCategoryRepository,
    ) {
    }

    #[Route('/admin/itemcategory', name: 'app_admin_itemcategory_list')]
    public function list(): Response
    {
        $categories = $this->itemCategoryRepository->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('itemcategory/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Items',
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/itemcategory/submit', name: 'app_admin_itemcategory_submit')]
    public function submit(Request $request): Response
    {
        $category = new ItemCategory();

        $form = $this->createForm(ItemCategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();

            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        return $this->render('itemcategory/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvelle catégorie',
            'form' => $form,
        ]);
    }

    #[Route('/admin/itemcategory/update/{id}', name: 'app_admin_itemcategory_update')]
    public function update(int $id, Request $request): Response
    {
        $category = $this->itemCategoryRepository->find($id);
        if (!$category) {
            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        $form = $this->createForm(ItemCategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        return $this->render('itemcategory/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier la catégorie',
            'form' => $form,
        ]);
    }

    #[Route('/admin/itemcategory/delete/{id}', name: 'app_admin_itemcategory_delete')]
    public function delete(int $id): Response
    {
        $category = $this->itemCategoryRepository->find($id);
        if ($category) {
            $this->em->remove($category);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_itemcategory_list');
    }

    #[Route('/admin/itemcategory/reorder', name: 'app_admin_itemcategory_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        foreach ($order as $index => $id) {
            $category = $this->itemCategoryRepository->find($id);
            if ($category) {
                $category->setSortOrder($index);
            }
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}
