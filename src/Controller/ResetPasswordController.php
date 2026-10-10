<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordType;
use App\Message\UserSyncMessage;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use App\Service\IdentityProvider;
use App\Service\LdapPasswordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password/{token}', name: 'app_reset_password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private IdentityProvider $identityProvider,
        private LdapPasswordService $ldapPasswordService,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        Request $request,
        string $token,
        UserRepository $userRepository,
        PasswordResetRequestRepository $resetRequestRepo,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->identityProvider->canManagePassword()) {
            $this->addFlash('error', 'La réinitialisation de mot de passe n\'est pas disponible : votre mot de passe est géré par votre fournisseur d\'identité.');

            return $this->redirectToRoute('app_login');
        }

        $resetRequest = $resetRequestRepo->findValidToken($token);

        if (!$resetRequest) {
            $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $resetRequest->getUser();
        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->setLdapPassword($this->ldapPasswordService->hashForLdap($plainPassword));
            $user->setOpenLdapPassword($this->ldapPasswordService->hashForOpenLdap($plainPassword));
            $user->setNeedsPasswordUpgrade(false);

            $resetRequest->setUsed(true);

            $em->flush();

            if ($this->identityProvider->isSyncEnabled()) {
                $this->bus->dispatch(new UserSyncMessage($user->getId()));
            }

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}