<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/admin/user', name: 'app_admin_user')]
    public function list(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('user/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Liste des Utilisateurs',
            'routesubmit' => 'app_admin_user_submit',
            'routeupdate' => 'app_admin_user_update',
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/submit', name: 'app_admin_user_submit')]
    public function submit(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();

        $form = $this->createForm(UserType::class, $user, ['mode' => 'submit', 'appModeAuth' => $this->getParameter('appModeAuth')]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $password = $user->getPassword();
            if ('CAS' === $this->getParameter('appModeAuth')) {
                $password = Uuid::uuid4();
            }

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );
            $user->setPassword($hashedPassword);

            $this->em->persist($user);
            $this->em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('user/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Utilisateur',
            'routecancel' => 'app_admin_user',
            'routedelete' => 'app_admin_user_delete',
            'mode' => 'submit',
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/update/{id}', name: 'app_admin_user_update')]
    public function update(int $id, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->redirectToRoute('app_admin_user');
        }
        $hashedPassword = $user->getPassword();

        $form = $this->createForm(UserType::class, $user, ['mode' => 'update', 'appModeAuth' => $this->getParameter('appModeAuth')]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($user->getPassword()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                );
            }
            $user->setPassword($hashedPassword);
            $this->em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('user/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Utilisateur = '.$user->getUsername(),
            'routecancel' => 'app_admin_user',
            'routedelete' => 'app_admin_user_delete',
            'mode' => 'update',
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/delete/{id}', name: 'app_admin_user_delete')]
    public function delete(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->redirectToRoute('app_admin_user');
        }

        // Tentative de suppression
        try {
            $this->em->remove($user);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addflash('error', $e->getMessage());

            return $this->redirectToRoute('app_admin_user_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_admin_user');
    }

    #[Route('/user/profil', name: 'app_user_profil')]
    public function profil(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->userRepository->find($this->getUser());
        if (!$user) {
            return $this->redirectToRoute('app_user');
        }
        $hashedPassword = $user->getPassword();

        $form = $this->createForm(UserType::class, $user, ['mode' => 'profil', 'appModeAuth' => $this->getParameter('appModeAuth')]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($user->getPassword()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                );
            }
            $user->setPassword($hashedPassword);

            $this->em->flush();

            return $this->redirectToRoute('app_user');
        }

        return $this->render('user/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Profil = '.$user->getUsername(),
            'routecancel' => 'app_user',
            'mode' => 'profil',
            'form' => $form,
            'user' => $user,
        ]);
    }
}