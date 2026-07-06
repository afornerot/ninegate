<?php

namespace App\Controller;

use App\Form\ResetPasswordType;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password', name: 'app_reset_password')]
class ResetPasswordController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $token,
        UserRepository $userRepository,
        PasswordResetRequestRepository $resetRequestRepo,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
    ): Response {
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

            // Hash password with bcrypt
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->setNeedsPasswordUpgrade(false);

            // Store SHA-256 for glauth
            $user->setSha256Hash(hash('sha256', $plainPassword));

            // Mark token as used
            $resetRequest->setUsed(true);

            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
