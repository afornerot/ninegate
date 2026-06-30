<?php

namespace App\Controller;

use App\Entity\Bookmark;
use App\Form\BookmarkType;
use App\Repository\BookmarkRepository;
use App\Voter\BookmarkVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookmarkController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookmarkRepository $bookmarkRepository,
    ) {
    }

    #[Route('/user/bookmark', name: 'app_user_bookmark_list')]
    public function list(): Response
    {
        $user = $this->getUser();
        $bookmarks = $user
            ? $this->bookmarkRepository->findBy(['user' => $user], ['title' => 'ASC'])
            : [];

        return $this->render('bookmark/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Mes Favoris',
            'bookmarks' => $bookmarks,
        ]);
    }

    #[Route('/user/bookmark/submit', name: 'app_user_bookmark_submit')]
    public function submit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_user_bookmark_list');
        }

        $bookmark = new Bookmark();
        $bookmark->setUser($user);

        $form = $this->createForm(BookmarkType::class, $bookmark);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($bookmark);
            $this->em->flush();

            return $this->redirectToRoute('app_user_bookmark_list');
        }

        return $this->render('bookmark/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouveau favori',
            'form' => $form,
        ]);
    }

    #[Route('/user/bookmark/update/{id}', name: 'app_user_bookmark_update')]
    public function update(int $id, Request $request): Response
    {
        $bookmark = $this->bookmarkRepository->find($id);
        if (!$bookmark || !$this->isGranted(BookmarkVoter::EDIT, $bookmark)) {
            return $this->redirectToRoute('app_user_bookmark_list');
        }

        $form = $this->createForm(BookmarkType::class, $bookmark);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_user_bookmark_list');
        }

        return $this->render('bookmark/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier le favori',
            'form' => $form,
        ]);
    }

    #[Route('/user/bookmark/delete/{id}', name: 'app_user_bookmark_delete')]
    public function delete(int $id): Response
    {
        $bookmark = $this->bookmarkRepository->find($id);
        if ($bookmark && $this->isGranted(BookmarkVoter::DELETE, $bookmark)) {
            $this->em->remove($bookmark);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_user_bookmark_list');
    }
}
