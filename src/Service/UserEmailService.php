<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@event-manager.local'
    ) {
    }

    public function sendWelcomeEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Manager'))
            ->to(new Address($user->getEmail(), $user->getFullName() ?? $user->getEmail()))
            ->subject('Welcome to Event Manager!')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    public function sendRegistrationConfirmation(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Manager'))
            ->to(new Address($user->getEmail(), $user->getFullName() ?? $user->getEmail()))
            ->subject('Registration Successful - Event Manager')
            ->htmlTemplate('emails/registration_confirmation.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }
}
