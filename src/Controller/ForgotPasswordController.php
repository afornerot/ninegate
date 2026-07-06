<?php

namespace App\Controller;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Form\ForgotPasswordType;
use App\Repository\UserRepository;
use App\Repository\PasswordResetRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/forgot-password', name: 'app_forgot_password')]
class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetRequestRepository $resetRequestRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(Request $request, MailerInterface $mailer, UrlGeneratorInterface $urlGenerator): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Always show success message to prevent email enumeration
            $this->addFlash('success', 'Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé.');

            if ($user) {
                // Invalidate previous tokens
                $oldTokens = $this->resetRequestRepo->findBy(['user' => $user, 'used' => false]);
                foreach ($oldTokens as $oldToken) {
                    $oldToken->setUsed(true);
                }

                // Create new token (24h expiry)
                $token = bin2hex(random_bytes(32));
                $resetRequest = new PasswordResetRequest();
                $resetRequest->setUser($user);
                $resetRequest->setToken($token);
                $resetRequest->setExpiresAt(new \DateTimeImmutable('+24 hours'));
                $this->em->persist($resetRequest);
                $this->em->flush();

                // Send email
                $resetUrl = $urlGenerator->generate('app_reset_password', [
                    'token' => $token,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $emailMessage = (new Email())
                    ->from('noreply@ninegate.local')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->text("Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nCliquez sur le lien suivant (valable 24h) :\n{$resetUrl}\n\nSi vous n'avez pas fait cette demande, ignorez cet email.")
                    ->html("<p>Bonjour,</p><p>Vous avez demandé la réinitialisation de votre mot de passe.</p><p><a href=\"{$resetUrl}\">Réinitialiser mon mot de passe</a></p><p><small>Ce lien est valable 24 heures.</small></p>");

                $mailer->send($emailMessage);
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }
}
