<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailVerificationIntegrationTest extends WebTestCase
{
    private static int $emailCounter = 1;

    private function getUniqueEmail(): string
    {
        return 'integration' . self::$emailCounter++ . '_' . uniqid() . '@test.com';
    }

    public function testCompleteEmailVerificationFlow(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $email = $this->getUniqueEmail();

        // Step 1: Register a new user
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register')->form();
        $client->submit($form, [
            'registration_form[email]' => $email,
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);

        // Should redirect to verification pending page
        self::assertResponseRedirects('/verification-pending');

        // Step 2: Get the user from database and check verification token exists
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertFalse($user->isEmailVerified());
        self::assertFalse($user->isActive());
        self::assertNotNull($user->getEmailVerificationToken());

        $token = $user->getEmailVerificationToken();

        // Step 3: Verify email
        $client->request('GET', '/verify-email/' . $token);

        // Should redirect to login with success message
        self::assertResponseRedirects('/login');

        // Step 4: Check user is now verified and active
        $entityManager->clear(); // Clear entity manager to force fresh fetch
        $verifiedUser = $entityManager->getRepository(User::class)->find($user->getId());

        if (!$verifiedUser->isEmailVerified()) {
            self::fail("User email verification status: " . ($verifiedUser->isEmailVerified() ? 'true' : 'false') .
                ", isActive: " . ($verifiedUser->isActive() ? 'true' : 'false') .
                ", token: " . ($verifiedUser->getEmailVerificationToken() ?? 'null'));
        }

        self::assertTrue($verifiedUser->isEmailVerified());
        self::assertTrue($verifiedUser->isActive());
        self::assertNull($verifiedUser->getEmailVerificationToken());

        // Step 5: Follow redirect and check for success message
        $crawler = $client->followRedirect();

        // Debug: Check what flash messages are present
        $successMessages = $crawler->filter('.bg-green-100');
        if ($successMessages->count() > 0) {
            $message = $successMessages->first()->text();
            if (!str_contains($message, 'Your email has been verified successfully')) {
                self::fail("Wrong flash message found: " . $message);
            }
        } else {
            self::fail("No success flash message found");
        }

        self::assertSelectorTextContains('.bg-green-100', 'Your email has been verified successfully');

        // Step 6: Try to login with the verified user - get fresh login form with CSRF token
        $crawler = $client->request('GET', '/login');

        // Find and submit login form
        $form = $crawler->selectButton('Sign in')->form();
        $client->submit($form, [
            'email' => $email,
            'password' => 'password123',
        ]);

        // Should redirect to default page after successful login
        self::assertResponseRedirects('/');

        // Follow redirect and check we're logged in
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testUnverifiedUserCannotLogin(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $email = $this->getUniqueEmail();

        // Step 1: Register a new user (but don't verify email)
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();
        $client->submit($form, [
            'registration_form[email]' => $email,
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);

        // Step 2: Get the login form to extract CSRF token
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();

        // Extract CSRF token from the form
        $csrfToken = $form->get('csrf_token')->getValue();

        // Step 3: Try to login without email verification using POST with CSRF token
        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'password123',
            'csrf_token' => $csrfToken,
        ]);

        // Should redirect back to login with error
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();

        // Should show error message about email verification
        self::assertSelectorExists('.bg-red-100');

        // Get the actual error message for debugging
        $errorMessage = $crawler->filter('.bg-red-100')->text();

        // Check for either the expected verification message or CSRF error for debugging
        $hasVerificationError = str_contains($errorMessage, 'email address is not verified');
        $hasCSRFError = str_contains($errorMessage, 'Invalid CSRF token');

        if ($hasCSRFError) {
            self::fail("Expected email verification error but got CSRF error: " . $errorMessage);
        }

        self::assertTrue($hasVerificationError, "Expected verification error, got: " . $errorMessage);
    }
}
