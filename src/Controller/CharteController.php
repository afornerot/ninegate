<?php

namespace App\Controller;

use App\Entity\Charte;
use App\Entity\CharteSignature;
use App\Form\CharteType;
use App\Repository\CharteRepository;
use App\Repository\CharteSignatureRepository;
use App\Voter\CharteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CharteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CharteRepository $charteRepository,
        private CharteSignatureRepository $charteSignatureRepository,
    ) {
    }

    #[Route('/admin/charte', name: 'app_admin_charte_list')]
    public function adminList(): Response
    {
        $chartes = $this->charteRepository->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('charte/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Chartes',
            'chartes' => $chartes,
            'isAdmin' => true,
        ]);
    }

    #[Route('/admin/charte/submit', name: 'app_admin_charte_submit')]
    public function adminSubmit(Request $request): Response
    {
        $charte = new Charte();
        $form = $this->createForm(CharteType::class, $charte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('allUsers')->getData()) {
                $charte->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $charte->getGroups()->clear();
            }

            $this->em->persist($charte);
            $this->em->flush();

            $this->addFlash('success', 'Charte créée avec succès');

            return $this->redirectToRoute('app_admin_charte_list');
        }

        return $this->render('charte/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvelle Charte',
            'form' => $form,
        ]);
    }

    #[Route('/admin/charte/update/{id}', name: 'app_admin_charte_update')]
    public function adminUpdate(int $id, Request $request): Response
    {
        $charte = $this->charteRepository->find($id);
        if (!$charte) {
            return $this->redirectToRoute('app_admin_charte_list');
        }

        $form = $this->createForm(CharteType::class, $charte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('allUsers')->getData()) {
                $charte->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $charte->getGroups()->clear();
            }

            $this->em->flush();

            $this->addFlash('success', 'Charte modifiée avec succès');

            return $this->redirectToRoute('app_admin_charte_list');
        }

        return $this->render('charte/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier la Charte',
            'form' => $form,
        ]);
    }

    #[Route('/admin/charte/delete/{id}', name: 'app_admin_charte_delete')]
    public function adminDelete(int $id): Response
    {
        $charte = $this->charteRepository->find($id);
        if ($charte) {
            $this->em->remove($charte);
            $this->em->flush();
            $this->addFlash('success', 'Charte supprimée');
        }

        return $this->redirectToRoute('app_admin_charte_list');
    }

    #[Route('/admin/charte/view/{id}', name: 'app_admin_charte_view')]
    public function adminView(int $id): Response
    {
        $charte = $this->charteRepository->find($id);
        if (!$charte) {
            return $this->redirectToRoute('app_admin_charte_list');
        }

        return $this->render('charte/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $charte->getTitle(),
            'charte' => $charte,
            'isAdmin' => true,
        ]);
    }

    #[Route('/admin/charte/removesignature/{charteId}/{userId}', name: 'app_admin_charte_removesignature')]
    public function adminRemoveSignature(int $charteId, int $userId): Response
    {
        $signature = $this->charteSignatureRepository->findOneBy([
            'charte' => $charteId,
            'user' => $userId,
        ]);

        if ($signature) {
            $this->em->remove($signature);
            $this->em->flush();
            $this->addFlash('success', 'Signature supprimée');
        }

        return $this->redirectToRoute('app_admin_charte_view', ['id' => $charteId]);
    }

    #[Route('/admin/charte/resetsignatures/{id}', name: 'app_admin_charte_resetsignatures')]
    public function adminResetSignatures(int $id): Response
    {
        $charte = $this->charteRepository->find($id);
        if ($charte) {
            foreach ($charte->getSignatures() as $signature) {
                $this->em->remove($signature);
            }
            $this->em->flush();
            $this->addFlash('success', 'Toutes les signatures ont été supprimées');
        }

        return $this->redirectToRoute('app_admin_charte_view', ['id' => $id]);
    }

    #[Route('/user/charte', name: 'app_user_charte_list')]
    public function userList(): Response
    {
        $user = $this->getUser();
        $allChartes = $this->charteRepository->findBy([], ['sortOrder' => 'ASC']);

        $chartes = array_filter($allChartes, fn(Charte $c) => $c->isAccessibleToUser($user));

        return $this->render('charte/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Mes Chartes',
            'chartes' => $chartes,
            'isAdmin' => false,
        ]);
    }

    #[Route('/user/charte/view/{id}', name: 'app_user_charte_view')]
    public function userView(int $id): Response
    {
        $charte = $this->charteRepository->find($id);
        if (!$charte) {
            return $this->redirectToRoute('app_user_charte_list');
        }

        $user = $this->getUser();
        if (!$charte->isAccessibleToUser($user)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        return $this->render('charte/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $charte->getTitle(),
            'charte' => $charte,
            'isAdmin' => false,
        ]);
    }

    #[Route('/user/charte/sign/{id}', name: 'app_user_charte_sign')]
    public function userSign(int $id): Response
    {
        $charte = $this->charteRepository->find($id);
        if (!$charte) {
            return $this->redirectToRoute('app_user_charte_list');
        }

        $user = $this->getUser();
        if (!$charte->isAccessibleToUser($user)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        if (!$charte->isRequireSignature() || $charte->hasUserSigned($user)) {
            return $this->redirectToRoute('app_user_charte_view', ['id' => $id]);
        }

        $signature = new CharteSignature();
        $signature->setCharte($charte);
        $signature->setUser($user);
        $this->em->persist($signature);
        $this->em->flush();

        $this->addFlash('success', 'Charte signée avec succès');

        $pendingChartes = $this->charteRepository->findBy(['requireSignature' => true], ['sortOrder' => 'ASC']);
        foreach ($pendingChartes as $pending) {
            if ($pending->isAccessibleToUser($user) && !$pending->hasUserSigned($user)) {
                return $this->redirectToRoute('app_charte_sign_required');
            }
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/user/charte/sign-required', name: 'app_charte_sign_required')]
    public function signRequired(): Response
    {
        $user = $this->getUser();
        $chartes = $this->charteRepository->findBy(['requireSignature' => true], ['sortOrder' => 'ASC']);

        $unsignedChartes = [];
        foreach ($chartes as $charte) {
            if ($charte->isAccessibleToUser($user) && !$charte->hasUserSigned($user)) {
                $unsignedChartes[] = $charte;
            }
        }

        return $this->render('charte/sign_required.html.twig', [
            'usemenu' => false,
            'usesidebar' => false,
            'title' => 'Signature requise',
            'chartes' => $unsignedChartes,
        ]);
    }

    #[Route('/user/charte/sign-all', name: 'app_charte_sign_all')]
    public function signAll(): Response
    {
        $user = $this->getUser();
        $chartes = $this->charteRepository->findBy(['requireSignature' => true], ['sortOrder' => 'ASC']);

        foreach ($chartes as $charte) {
            if ($charte->isAccessibleToUser($user) && !$charte->hasUserSigned($user)) {
                $signature = new CharteSignature();
                $signature->setCharte($charte);
                $signature->setUser($user);
                $this->em->persist($signature);
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Toutes les chartes ont été signées');

        return $this->redirectToRoute('app_home');
    }
}
