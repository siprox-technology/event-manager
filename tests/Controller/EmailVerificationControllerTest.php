<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailVerificationControllerTest extends WebTestCase
{
    private static int $emailCounter = 1;

    private function getUniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . self::$emailCounter++ . '_' . uniqid() . '@verification.com';
    }

    private function createTestUser(EntityManagerInterface $entityManager, string $email, bool $isVerified = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashedpassword');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $user->setIsActive($isVerified);
        $user->setIsEmailVerified($isVerified);
        
        if (!$isVerified) {
            $user->generateEmailVerificationToken();
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
    
    public function testVerifyEmailWithValidToken(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Create a user with an unverified email
        $email = $this->getUniqueEmail();
        $user = $this->createTestUser($entityManager, $email);
        $token = $user->getEmailVerificationToken();
        $userId = $user->getId();

        // Visit the verification URL
        $client->request('GET', '/verify-email/' . $token);

        // Should redirect to login with success message
        self::assertResponseRedirects('/login');

        // Follow the redirect
        $client->followRedirect();

        // Check for success flash message
        self::assertSelectorTextContains('.bg-green-100', 'Your email has been verified successfully');

        // Check that user is now verified and active by fetching fresh from database
        $verifiedUser = $entityManager->getRepository(User::class)->find($userId);
        self::assertTrue($verifiedUser->isEmailVerified());
        self::assertTrue($verifiedUser->isActive());
        self::assertNull($verifiedUser->getEmailVerificationToken());
        self::assertNull($verifiedUser->getEmailVerificationTokenExpiresAt());
    }

    public function testVerifyEmailWithInvalidToken(): void
    {
        $client = static::createClient();

        // Try to verify with an invalid token
        $client->request('GET', '/verify-email/invalid-token');

        // Should redirect to resend verification page
        self::assertResponseRedirects('/resend-verification');

        // Follow the redirect
        $client->followRedirect();

        // Check for error flash message
        self::assertSelectorTextContains('.bg-red-100', 'Invalid or expired verification link');
    }

    public function testVerifyEmailWithExpiredToken(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Create a user with an expired verification token
        $email = $this->getUniqueEmail('expired');
        $user = $this->createTestUser($entityManager, $email);
        
        // Set expiration date in the past
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $entityManager->flush();
        
        $token = $user->getEmailVerificationToken();

        // Try to verify with the expired token
        $client->request('GET', '/verify-email/' . $token);

        // Should redirect to resend verification page
        self::assertResponseRedirects('/resend-verification');
    }

    public function testResendVerificationEmail(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Create a user with an unverified email
        $email = $this->getUniqueEmail('resend');
        $user = $this->createTestUser($entityManager, $email);

        // Visit the resend verification page
        $crawler = $client->request('GET', '/resend-verification');
        self::assertResponseIsSuccessful();

        // Submit the form with the user's email
        $form = $crawler->selectButton('Send Verification Email')->form();
        $client->submit($form, [
            'email' => $user->getEmail(),
        ]);

        // Should stay on the same page with success message
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.bg-green-100', 'A new verification email has been sent');
    }

    public function testResendVerificationEmailForAlreadyVerifiedUser(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Create a user with a verified email
        $email = $this->getUniqueEmail('verified');
        $user = $this->createTestUser($entityManager, $email, true);

        // Visit the resend verification page
        $crawler = $client->request('GET', '/resend-verification');
        
        // Submit the form with the verified user's email
        $form = $crawler->selectButton('Send Verification Email')->form();
        $client->submit($form, [
            'email' => $user->getEmail(),
        ]);

        // Should redirect to login page
        self::assertResponseRedirects('/login');

        // Follow the redirect
        $client->followRedirect();

        // Check for info message
        self::assertSelectorTextContains('.bg-blue-100', 'Your email is already verified');
    }

    public function testVerificationPendingPage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/verification-pending');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Check Your Email');
        self::assertSelectorExists('a[href="/resend-verification"]');
    }
}
