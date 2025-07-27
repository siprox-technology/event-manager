<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserManagementFunctionalTest extends WebTestCase
{
    public function testRegistrationPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Register');
        self::assertSelectorTextContains('h2', 'Create your account');
    }

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Log in');
        self::assertSelectorTextContains('h2', 'Sign in to your account');
    }

    public function testHomepageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Welcome to Event Manager');
        self::assertSelectorTextContains('h1', 'Welcome to Event Manager');
    }

    public function testProfilePageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/');

        // Should be redirected to login
        self::assertResponseRedirects();
    }

    public function testNavigationLinksExist(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        
        // Check for navigation links when not authenticated
        self::assertGreaterThan(0, $crawler->filter('a[href*="login"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href*="register"]')->count());
    }

    public function testRegistrationFormExists(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        
        // Check that all required form fields exist
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[email]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[firstName]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[lastName]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[plainPassword][first]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[plainPassword][second]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="registration_form[agreeTerms]"]')->count());
    }

    public function testLoginFormExists(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        
        // Check that login form fields exist
        self::assertGreaterThan(0, $crawler->filter('input[name="email"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="password"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="csrf_token"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="_remember_me"]')->count());
    }

    public function testKeyFeaturesDisplayed(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        
        // Check that key features section exists
        self::assertSelectorTextContains('h2', 'Key Features');
        
        // Check for specific feature titles (use more specific selectors)
        $crawler = $client->getCrawler();
        $h3Elements = $crawler->filter('h3');
        
        $foundEventManagement = false;
        $foundUserManagement = false;
        $foundContentManagement = false;
        
        foreach ($h3Elements as $h3) {
            $text = $h3->textContent;
            if (strpos($text, 'Event Management') !== false) {
                $foundEventManagement = true;
            }
            if (strpos($text, 'User Management') !== false) {
                $foundUserManagement = true;
            }
            if (strpos($text, 'Content Management') !== false) {
                $foundContentManagement = true;
            }
        }
        
        self::assertTrue($foundEventManagement, 'Event Management section not found');
        self::assertTrue($foundUserManagement, 'User Management section not found');
        self::assertTrue($foundContentManagement, 'Content Management section not found');
    }
}
