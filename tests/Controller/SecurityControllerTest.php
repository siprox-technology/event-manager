<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Sign in to your account');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
        self::assertSelectorExists('input[name="csrf_token"]');
    }

    public function testLoginPageHasRegisterLink(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        // Look for the content area register link, not the navigation link
        self::assertStringContainsString('create a new account', $client->getResponse()->getContent());
        self::assertSelectorExists('a[href*="register"]');
    }

    public function testRememberMeCheckbox(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_remember_me"]');
        self::assertSelectorExists('label[for="remember-me"]');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();

        // Submit login form with invalid credentials
        $client->submit($form, [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        // Should redirect back to login with error
        self::assertResponseRedirects('/login');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-red-100'); // Error message container
    }

    // More complex tests involving user creation would require proper test database setup
    public function testLoginFormHasCorrectAction(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form();
        self::assertStringContainsString('/login', $form->getUri());
    }
}
