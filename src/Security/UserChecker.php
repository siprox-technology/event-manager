<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check email verification first, as unverified users are also inactive
        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Your email address is not verified. Please check your email and click the verification link before logging in.'
            );
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Your account is not active.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Additional post-authentication checks can be added here if needed
    }
}
