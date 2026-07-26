<?php

namespace App\Controller\Widgets;

use App\Entity\AnnonceHidden;
use App\Entity\User;
use App\Repository\AnnonceRepository;
use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnonceWidgetController extends AbstractController
{
    public function __construct(
        private AnnonceRepository $annonceRepository,
    ) {
    }

    #[Route('/user/widget/annonce/{pageWidgetId}', name: 'app_user_pagewidget_annonce')]
    #[Route('/admin/widget/annonce/{pageWidgetId}', name: 'app_admin_pagewidget_annonce')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);
        $user = $this->getUser();

        $allAnnonces = $this->annonceRepository->findBy([], ['sortOrder' => 'ASC']);
        $annonces = [];
        foreach ($allAnnonces as $annonce) {
            if ($annonce->isAccessibleToUser($user) && (!$user || !$annonce->isHiddenByUser($user))) {
                $annonces[] = $annonce;
            }
        }

        if (empty($annonces)) {
            return new Response('');
        }

        return $this->render('widget/annonce.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'annonces' => $annonces,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user/widget/annonce/hide/{annonceId}', name: 'app_user_pagewidget_annonce_hide', methods: ['POST'])]
    public function hide(int $annonceId, EntityManagerInterface $em, AnnonceRepository $annonceRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non connecté'], 401);
        }

        $annonce = $annonceRepository->find($annonceId);
        if (!$annonce) {
            return new JsonResponse(['error' => 'Annonce introuvable'], 404);
        }

        if (!$annonce->isCanBeHidden()) {
            return new JsonResponse(['error' => 'Cette annonce ne peut pas être masquée'], 403);
        }

        $existing = $em->getRepository(AnnonceHidden::class)->findOneBy([
            'annonce' => $annonce,
            'user' => $user,
        ]);

        if (!$existing) {
            $hidden = new AnnonceHidden();
            $hidden->setAnnonce($annonce);
            $hidden->setUser($user);
            $em->persist($hidden);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}
