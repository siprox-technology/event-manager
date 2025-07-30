<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private UserEmailService $userEmailService,
        private string $fromEmail = 'noreply@event-manager.local'
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw new \InvalidArgumentException('User email is already verified.');
        }

        $token = $user->generateEmailVerificationToken();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Manager'))
            ->to(new Address($user->getEmail(), $user->getFullName() ?? $user->getEmail()))
            ->subject('Please verify your email address - Event Manager')
            ->htmlTemplate('emails/email_verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $user->getEmailVerificationTokenExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function verifyEmailWithToken(string $token): bool
    {
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            return false;
        }

        if ($user->isEmailVerificationTokenExpired()) {
            return false;
        }

        if ($user->isEmailVerified()) {
            return true; // Already verified
        }

        // Verify the user
        $user->setIsEmailVerified(true);
        $user->setIsActive(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationTokenExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send welcome email after successful verification
        try {
            $this->userEmailService->sendWelcomeEmail($user);
        } catch (\Exception $e) {
            // Log the error but don't fail the verification
            // In a real application, you'd log this properly
        }

        return true;
    }

    public function resendVerificationEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw new \InvalidArgumentException('User email is already verified.');
        }

        $this->sendVerificationEmail($user);
    }
}
