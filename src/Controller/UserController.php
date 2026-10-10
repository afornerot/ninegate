<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Message\UserSyncMessage;
use App\Repository\UserRepository;
use App\Service\IdentityProvider;
use App\Service\LdapPasswordService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private IdentityProvider $identityProvider,
        private LdapPasswordService $ldapPasswordService,
        private MessageBusInterface $bus,
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
            'identityProvider' => $this->identityProvider,
        ]);
    }

    #[Route('/admin/user/submit', name: 'app_admin_user_submit')]
    public function submit(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if (!$this->identityProvider->canCreateUser()) {
            $this->addFlash('error', sprintf(
                'Impossible de créer un utilisateur : la source d\'identité est gérée par %s.',
                $this->identityProvider->getMasterIdentity()
            ));

            return $this->redirectToRoute('app_admin_user');
        }

        $user = new User();

        $form = $this->createForm(UserType::class, $user, [
            'mode' => 'submit',
            'appModeAuth' => $this->identityProvider->getModeAuth(),
            'appMasterIdentity' => $this->identityProvider->getMasterIdentity(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $password = $user->getPassword();
            if (IdentityProvider::MODE_SQL !== $this->identityProvider->getModeAuth()) {
                $password = Uuid::uuid4();
            }

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );
            $user->setPassword($hashedPassword);
            $user->setLdapPassword($this->ldapPasswordService->hashForLdap($password));
            $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($password));
            $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($password));

            $this->em->persist($user);
            $this->em->flush();

            $this->dispatchUserSync($user);

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

        $form = $this->createForm(UserType::class, $user, [
            'mode' => 'update',
            'appModeAuth' => $this->identityProvider->getModeAuth(),
            'appMasterIdentity' => $this->identityProvider->getMasterIdentity(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($user->getPassword()) {
                $plainPassword = $user->getPassword();
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plainPassword
                );
                $user->setPassword($hashedPassword);
                $user->setLdapPassword($this->ldapPasswordService->hashForLdap($plainPassword));
                $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($plainPassword));
            }
            $this->em->flush();

            $this->dispatchUserSync($user);

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
            'identityProvider' => $this->identityProvider,
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

        $form = $this->createForm(UserType::class, $user, [
            'mode' => 'profil',
            'appModeAuth' => $this->identityProvider->getModeAuth(),
            'appMasterIdentity' => $this->identityProvider->getMasterIdentity(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($user->getPassword()) {
                $plainPassword = $user->getPassword();
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plainPassword
                );
                $user->setPassword($hashedPassword);
                $user->setLdapPassword($this->ldapPasswordService->hashForLdap($plainPassword));
                $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($plainPassword));
            }

            $this->em->flush();

            $this->dispatchUserSync($user);

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
            'identityProvider' => $this->identityProvider,
        ]);
    }

    #[Route('/admin/user/regenerate-apikey/{id}', name: 'app_admin_user_regenerate_apikey')]
    #[Route('/user/regenerate-apikey', name: 'app_user_regenerate_apikey')]
    public function regenerateApiKey(int $id = null): Response
    {
        if ($id) {
            $user = $this->userRepository->find($id);
        } else {
            $user = $this->userRepository->find($this->getUser()->getId());
        }

        if (!$user) {
            return $this->redirectToRoute('app_user');
        }

        $user->generateApiKey();
        $this->em->flush();

        $this->addFlash('success', 'Clé API régénérée avec succès');

        if ($id) {
            return $this->redirectToRoute('app_admin_user_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_user_profil');
    }

    private function dispatchUserSync(User $user): void
    {
        if ($this->identityProvider->isSyncEnabled()) {
            $this->bus->dispatch(new UserSyncMessage($user->getId()));
        }
    }
}