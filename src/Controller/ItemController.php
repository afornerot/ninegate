<?php

namespace App\Controller;

use App\Entity\Item;
use App\Form\ItemType;
use App\Repository\ItemCategoryRepository;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemRepository $itemRepository,
        private ItemCategoryRepository $itemCategoryRepository,
    ) {
    }

    #[Route('/admin/item/submit/{categoryId}', name: 'app_admin_item_submit')]
    public function submit(int $categoryId, Request $request): Response
    {
        $category = $this->itemCategoryRepository->find($categoryId);
        if (!$category) {
            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        $item = new Item();
        $item->setCategory($category);

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle "Tout le monde" checkbox
            if ($form->get('allUsers')->getData()) {
                $item->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $item->getGroups()->clear();
            }

            $this->em->persist($item);
            $this->em->flush();

            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        return $this->render('item/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvel item',
            'form' => $form,
            'categoryId' => $categoryId,
        ]);
    }

    #[Route('/admin/item/update/{categoryId}/{id}', name: 'app_admin_item_update')]
    public function update(int $categoryId, int $id, Request $request): Response
    {
        $item = $this->itemRepository->find($id);
        if (!$item) {
            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle "Tout le monde" checkbox
            if ($form->get('allUsers')->getData()) {
                $item->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $item->getGroups()->clear();
            }

            $this->em->flush();

            return $this->redirectToRoute('app_admin_itemcategory_list');
        }

        return $this->render('item/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier l\'item',
            'form' => $form,
            'categoryId' => $categoryId,
        ]);
    }

    #[Route('/admin/item/delete/{categoryId}/{id}', name: 'app_admin_item_delete')]
    public function delete(int $categoryId, int $id): Response
    {
        $item = $this->itemRepository->find($id);
        if ($item) {
            $this->em->remove($item);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_itemcategory_list');
    }

    #[Route('/admin/item/move/{id}', name: 'app_admin_item_move', methods: ['POST'])]
    public function move(int $id, Request $request): JsonResponse
    {
        $item = $this->itemRepository->find($id);
        if (!$item) {
            return new JsonResponse(['success' => false, 'error' => 'Item introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newCategoryId = $data['categoryId'] ?? null;
        $newOrder = $data['order'] ?? null;

        if ($newCategoryId !== null) {
            $newCategory = $this->itemCategoryRepository->find($newCategoryId);
            if ($newCategory) {
                $item->setCategory($newCategory);
            }
        }

        if ($newOrder !== null) {
            $item->setSortOrder($newOrder);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}
