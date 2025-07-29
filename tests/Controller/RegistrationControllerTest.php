<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    private function getEntityManager(): EntityManagerInterface
    {
        $kernel = self::bootKernel();
        return $kernel->getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Clean up any test users created
        if (self::$kernel) {
            $entityManager = $this->getEntityManager();
            $testUsers = $entityManager->getRepository(User::class)->findBy([
                'email' => ['test@registration.com', 'duplicate@test.com']
            ]);

            foreach ($testUsers as $user) {
                $entityManager->remove($user);
            }
            $entityManager->flush();
            $entityManager->close();
        }

        parent::tearDown();
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Create your account');
        self::assertSelectorExists('input[name="registration_form[email]"]');
        self::assertSelectorExists('input[name="registration_form[firstName]"]');
        self::assertSelectorExists('input[name="registration_form[lastName]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][first]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][second]"]');
        self::assertSelectorExists('input[name="registration_form[agreeTerms]"]');
    }

    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        $client->submit($form, [
            'registration_form[email]' => 'test@registration.com',
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);

        // Should redirect to verification pending page after successful registration
        self::assertResponseRedirects('/verification-pending');

        // Check that user was created in database
        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'email' => 'test@registration.com'
        ]);

        self::assertNotNull($user);
        self::assertSame('test@registration.com', $user->getEmail());
        self::assertSame('Test', $user->getFirstName());
        self::assertSame('User', $user->getLastName());
        self::assertContains('ROLE_USER', $user->getRoles());
        
        // Check that user is not yet active and email is not verified
        self::assertFalse($user->isActive());
        self::assertFalse($user->isEmailVerified());
        self::assertNotNull($user->getEmailVerificationToken());
        self::assertNotNull($user->getEmailVerificationTokenExpiresAt());

        // Verify password was hashed
        self::assertNotSame('password123', $user->getPassword());
        self::assertNotEmpty($user->getPassword());
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        $client->submit($form, [
            'registration_form[email]' => 'invalid-email',
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        // Check for form errors - Symfony default error format
        self::assertSelectorExists('ul li, .form-error');
    }

    public function testRegistrationWithMismatchedPasswords(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        $client->submit($form, [
            'registration_form[email]' => 'test@mismatch.com',
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'differentpassword',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        // Check for form errors - Symfony default error format  
        self::assertSelectorExists('ul li, .form-error');
    }

    public function testRegistrationWithoutAgreeingToTerms(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        $client->submit($form, [
            'registration_form[email]' => 'test@terms.com',
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => false,
        ]);

        self::assertResponseIsUnprocessable();
        // Check for form errors - Symfony default error format
        self::assertSelectorExists('ul li, .form-error');
    }

    public function testRegistrationWithDuplicateEmail(): void
    {
        $client = static::createClient();

        // Create an existing user
        $existingUser = new User();
        $existingUser->setEmail('duplicate@test.com');
        $existingUser->setFirstName('Existing');
        $existingUser->setLastName('User');
        $existingUser->setPassword('hashedpassword');
        $existingUser->setRoles(['ROLE_USER']);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($existingUser);
        $entityManager->flush();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        $client->submit($form, [
            'registration_form[email]' => 'duplicate@test.com',
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsSuccessful();
        // Check that the form shows the error message via flash
        self::assertSelectorTextContains('.bg-red-100', 'This email address is already registered');
    }

    public function testRegistrationPageHasLoginLink(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        // Look for the content area login link, not the navigation link
        self::assertStringContainsString('sign in to your existing account', $client->getResponse()->getContent());
        self::assertSelectorExists('a[href*="login"]');
    }

    public function testRequiredFields(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();

        // Submit empty form
        $client->submit($form, []);

        self::assertResponseIsUnprocessable();
        // Check for form errors - Symfony default error format
        self::assertSelectorExists('ul li, .form-error');
    }
}
