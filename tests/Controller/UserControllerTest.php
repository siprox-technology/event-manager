<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private function getEntityManager(): EntityManagerInterface
    {
        // Use existing kernel if available, otherwise boot a new one
        $kernel = self::$kernel ?? self::bootKernel();
        return $kernel->getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        if (self::$kernel) {
            // Clean up test users - remove all users with @test.com emails
            $entityManager = $this->getEntityManager();
            $testUsers = $entityManager->getRepository(User::class)->createQueryBuilder('u')
                ->where('u.email LIKE :pattern')
                ->setParameter('pattern', '%@test.com')
                ->getQuery()
                ->getResult();

            foreach ($testUsers as $user) {
                $entityManager->remove($user);
            }
            $entityManager->flush();
            $entityManager->close();
        }

        parent::tearDown();
    }

    private function createTestUser(string $email = 'profile@test.com', array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');

        // Use a simple hash for testing instead of the password hasher service
        $user->setPassword('$2y$13$hashed_password_for_testing');
        $user->setRoles($roles);
        $user->setBio('This is a test bio');

        $entityManager = $this->getEntityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function testProfilePageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/');

        // Should redirect to login page
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertRouteSame('app_login');
    }

    public function testAuthenticatedUserCanViewProfile(): void
    {
        $client = static::createClient();

        // Create test user after client is set up
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/profile/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'User Profile');

        // Check for content on the page without specific selectors
        self::assertStringContainsString('Test User', $client->getResponse()->getContent());
        self::assertStringContainsString('profile@test.com', $client->getResponse()->getContent());
        self::assertStringContainsString('This is a test bio', $client->getResponse()->getContent());

        // Check for edit link
        self::assertSelectorExists('a[href*="edit"]');
    }

    public function testProfileDisplaysUserRole(): void
    {
        $client = static::createClient();

        // Test regular user
        $user = $this->createTestUser();
        $client->loginUser($user);
        $client->request('GET', '/profile/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-green-100'); // User role badge
        self::assertSelectorTextContains('.bg-green-100', 'User');

        // Test admin user
        $adminUser = $this->createTestUser('admin@test.com', ['ROLE_ADMIN']);
        $client->loginUser($adminUser);
        $client->request('GET', '/profile/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-red-100'); // Admin role badge
        self::assertSelectorTextContains('.bg-red-100', 'Administrator');
    }

    public function testProfileDisplaysMemberSinceDate(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/profile/');

        self::assertResponseIsSuccessful();
        // Check that "Member since" text exists in the page
        self::assertStringContainsString('Member since', $client->getResponse()->getContent());

        // Should display the creation date
        $expectedDate = $user->getCreatedAt()->format('F j, Y');
        self::assertStringContainsString($expectedDate, $client->getResponse()->getContent());
    }

    public function testEditProfilePageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/edit');

        // Should redirect to login page
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertRouteSame('app_login');
    }

    public function testAuthenticatedUserCanAccessEditProfile(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'edit_access_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);

        $client->loginUser($user);
        $client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', 'Edit Profile');
        self::assertSelectorExists('input[name="user_profile[email]"]');
        self::assertSelectorExists('input[name="user_profile[firstName]"]');
        self::assertSelectorExists('input[name="user_profile[lastName]"]');
        self::assertSelectorExists('textarea[name="user_profile[bio]"]');
    }

    public function testEditProfileFormIsPreFilledWithUserData(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'edit_prefilled_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/profile/edit');

        $form = $crawler->selectButton('Update Profile')->form();

        self::assertSame($uniqueEmail, $form['user_profile[email]']->getValue());
        self::assertSame('Test', $form['user_profile[firstName]']->getValue());
        self::assertSame('User', $form['user_profile[lastName]']->getValue());
        self::assertSame('This is a test bio', $form['user_profile[bio]']->getValue());
    }

    public function testSuccessfulProfileUpdate(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'edit_update_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Update Profile')->form();

        $updatedEmail = 'updated_' . uniqid() . '@test.com';
        $client->submit($form, [
            'user_profile[email]' => $updatedEmail,
            'user_profile[firstName]' => 'Updated',
            'user_profile[lastName]' => 'Name',
            'user_profile[bio]' => 'Updated bio content',
        ]);

        // Should redirect back to profile
        self::assertResponseRedirects('/profile/');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-green-100'); // Success message

        // Verify data was updated in database
        $entityManager = $this->getEntityManager();
        $updatedUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $updatedEmail]);
        self::assertNotNull($updatedUser);
        self::assertSame($updatedEmail, $updatedUser->getEmail());
        self::assertSame('Updated', $updatedUser->getFirstName());
        self::assertSame('Name', $updatedUser->getLastName());
        self::assertSame('Updated bio content', $updatedUser->getBio());
    }

    public function testProfileUpdateWithInvalidEmail(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'invalid_test_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Update Profile')->form();

        $client->submit($form, [
            'user_profile[email]' => 'invalid-email',
            'user_profile[firstName]' => 'Updated',
            'user_profile[lastName]' => 'Name',
            'user_profile[bio]' => 'Updated bio',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertSelectorExists('ul li, .form-error');
    }

    public function testEditProfileHasCancelLink(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'cancel_test_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);

        $client->loginUser($user);
        $client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        // Check for the cancel link - look for any link that contains "Cancel" text
        self::assertStringContainsString('Cancel', $client->getResponse()->getContent());
        self::assertStringContainsString('href="/profile/"', $client->getResponse()->getContent());
    }

    public function testProfileWithoutBioDoesNotShowBioSection(): void
    {
        $client = static::createClient();
        $uniqueEmail = 'nobio_test_' . uniqid() . '@test.com';
        $user = $this->createTestUser($uniqueEmail);
        $user->setBio(null); // Remove bio

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/profile/');

        self::assertResponseIsSuccessful();
        // Bio section should not be present when bio is empty
        $bioSections = $client->getCrawler()->filter('dt:contains("Bio")');
        self::assertCount(0, $bioSections);
    }
}
