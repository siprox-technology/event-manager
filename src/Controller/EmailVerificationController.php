<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): Response
    {
        $isVerified = $this->emailVerificationService->verifyEmailWithToken($token);

        if ($isVerified) {
            $this->addFlash('success', 'Your email has been verified successfully! You can now log in to your account.');

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('error', 'Invalid or expired verification link. Please request a new verification email.');

        return $this->redirectToRoute('app_resend_verification');
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function resendVerification(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please provide a valid email address.');

                return $this->redirectToRoute('app_resend_verification');
            }

            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if (!$user) {
                // Don't reveal if email exists for security
                $this->addFlash('info', 'If an account with that email exists and is not yet verified, a verification email has been sent.');

                return $this->redirectToRoute('app_resend_verification');
            }

            if ($user->isEmailVerified()) {
                $this->addFlash('info', 'Your email is already verified. You can log in to your account.');

                return $this->redirectToRoute('app_login');
            }

            try {
                $this->emailVerificationService->resendVerificationEmail($user);
                $this->addFlash('success', 'A new verification email has been sent to your email address.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to send verification email. Please try again later.');
            }
        }

        return $this->render('registration/resend_verification.html.twig');
    }

    #[Route('/verification-pending', name: 'app_verification_pending', methods: ['GET'])]
    public function verificationPending(): Response
    {
        return $this->render('registration/verification_pending.html.twig');
    }
}
