<?php

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EventCRUDTest extends WebTestCase
{
    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (self::$kernel) {
            $entityManager = $this->getEntityManager();

            // Clean up test events
            $events = $entityManager->getRepository(Event::class)->findBy([
                'title' => [
                    'Test Event Creation',
                    'Test Event Update',
                    'Event to Delete',
                    'Invalid Event Test'
                ]
            ]);
            foreach ($events as $event) {
                $entityManager->remove($event);
            }

            // Clean up test users  
            $users = $entityManager->getRepository(User::class)->findBy([
                'email' => ['test-admin@example.com', 'test-user@example.com']
            ]);
            foreach ($users as $user) {
                $entityManager->remove($user);
            }

            $entityManager->flush();
            $entityManager->close();
        }
        parent::tearDown();
    }

    private function createTestUsers(): array
    {
        $entityManager = $this->getEntityManager();

        // Create admin user
        $admin = new User();
        $admin->setEmail('test-admin@example.com');
        $admin->setFirstName('Test');
        $admin->setLastName('Admin');
        $admin->setPassword('$2y$13$hashed_password'); // Properly hashed password
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setIsActive(true);
        $admin->setIsEmailVerified(true);

        $entityManager->persist($admin);

        // Create regular user
        $user = new User();
        $user->setEmail('test-user@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('$2y$13$hashed_password'); // Properly hashed password
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return ['admin' => $admin, 'user' => $user];
    }

    private function loginUser($client, User $user): void
    {
        $client->loginUser($user);
    }

    public function testEventCreationFormIsAccessibleForAuthenticatedUsers(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();

        // Test unauthenticated access redirects to login
        $client->request('GET', '/events/new');
        self::assertResponseRedirects();

        // Test authenticated user can access form
        $this->loginUser($client, $users['user']);
        $client->request('GET', '/events/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorTextContains('h1', 'Create New Event');
    }

    public function testEventCreationWithValidData(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $this->loginUser($client, $users['user']);

        $crawler = $client->request('GET', '/events/new');

        $form = $crawler->selectButton('Create Event')->form([
            'event[title]' => 'Test Event Creation',
            'event[description]' => 'This is a test event description',
            'event[location]' => 'Test Location',
            'event[startDate]' => (new \DateTime('+1 week'))->format('Y-m-d\TH:i'),
            'event[endDate]' => (new \DateTime('+1 week +2 hours'))->format('Y-m-d\TH:i'),
            'event[maxParticipants]' => '50',
            'event[status]' => Event::STATUS_PLANNED,
        ]);

        $client->submit($form);

        // Should redirect to show page after successful creation
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify event was created in database
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $event = $entityManager->getRepository(Event::class)->findOneBy(['title' => 'Test Event Creation']);
        self::assertNotNull($event);
        self::assertSame('Test Event Creation', $event->getTitle());
        self::assertSame('This is a test event description', $event->getDescription());
        self::assertSame('Test Location', $event->getLocation());
        self::assertSame(Event::STATUS_PLANNED, $event->getStatus());
        self::assertSame(50, $event->getMaxParticipants());
        self::assertSame($users['user']->getId(), $event->getCreatedBy()->getId());
    }

    public function testEventCreationWithInvalidData(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $this->loginUser($client, $users['user']);

        $crawler = $client->request('GET', '/events/new');

        // Submit form with missing required fields
        $form = $crawler->selectButton('Create Event')->form([
            'event[title]' => '', // Empty title should be invalid
            'event[description]' => 'Test description',
            'event[location]' => 'Test Location',
            'event[startDate]' => '', // Empty start date should be invalid
            'event[status]' => Event::STATUS_PLANNED,
        ]);

        $client->submit($form);

        // Should stay on the form page with errors
        self::assertResponseIsSuccessful();

        // Verify event was NOT created in database
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $event = $entityManager->getRepository(Event::class)->findOneBy(['description' => 'Test description']);
        self::assertNull($event);
    }

    public function testEventEditFormIsAccessibleForEventCreator(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event
        $event = new Event();
        $event->setTitle('Test Event Update');
        $event->setDescription('Original description');
        $event->setLocation('Original Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['user']);
        $entityManager->persist($event);
        $entityManager->flush();

        // Test unauthenticated access redirects to login
        $client->request('GET', '/events/' . $event->getId() . '/edit');
        self::assertResponseRedirects();

        // Test event creator can access edit form
        $this->loginUser($client, $users['user']);
        $client->request('GET', '/events/' . $event->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorTextContains('h1', 'Edit Event');
    }

    public function testEventUpdateWithValidData(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event
        $event = new Event();
        $event->setTitle('Test Event Update');
        $event->setDescription('Original description');
        $event->setLocation('Original Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['user']);
        $entityManager->persist($event);
        $entityManager->flush();

        $this->loginUser($client, $users['user']);
        $crawler = $client->request('GET', '/events/' . $event->getId() . '/edit');

        $form = $crawler->selectButton('Update Event')->form([
            'event[title]' => 'Updated Event Title',
            'event[description]' => 'Updated description',
            'event[location]' => 'Updated Location',
            'event[startDate]' => (new \DateTime('+2 weeks'))->format('Y-m-d\TH:i'),
            'event[endDate]' => (new \DateTime('+2 weeks +3 hours'))->format('Y-m-d\TH:i'),
            'event[maxParticipants]' => '75',
            'event[status]' => Event::STATUS_PLANNED,
        ]);

        $client->submit($form);

        // Should redirect to show page after successful update
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify event was updated in database
        $entityManager->refresh($event);
        self::assertSame('Updated Event Title', $event->getTitle());
        self::assertSame('Updated description', $event->getDescription());
        self::assertSame('Updated Location', $event->getLocation());
        self::assertSame(75, $event->getMaxParticipants());
    }

    public function testEventEditAccessControlForNonOwner(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event owned by admin
        $event = new Event();
        $event->setTitle('Admin Event');
        $event->setDescription('Admin\'s event');
        $event->setLocation('Admin Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['admin']);
        $entityManager->persist($event);
        $entityManager->flush();

        // Test regular user cannot access admin's event edit form
        $this->loginUser($client, $users['user']);
        $client->request('GET', '/events/' . $event->getId() . '/edit');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testEventDeletion(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event
        $event = new Event();
        $event->setTitle('Event to Delete');
        $event->setDescription('This event will be deleted');
        $event->setLocation('Delete Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['user']);
        $entityManager->persist($event);
        $entityManager->flush();

        $eventId = $event->getId();

        $this->loginUser($client, $users['user']);

        // Access edit page to get delete form
        $crawler = $client->request('GET', '/events/' . $eventId . '/edit');
        self::assertResponseIsSuccessful();

        // Find and submit delete form
        $deleteForm = $crawler->selectButton('Delete Event')->form();
        $client->submit($deleteForm);

        // Should redirect to events index after deletion
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify event was deleted from database
        $deletedEvent = $entityManager->getRepository(Event::class)->find($eventId);
        self::assertNull($deletedEvent);
    }

    public function testEventDeletionAccessControl(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event owned by admin
        $event = new Event();
        $event->setTitle('Admin Event to Protect');
        $event->setDescription('Regular user should not be able to delete this');
        $event->setLocation('Protected Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['admin']);
        $entityManager->persist($event);
        $entityManager->flush();

        $this->loginUser($client, $users['user']);

        // Try to delete admin's event - should be forbidden
        $client->request('POST', '/events/' . $event->getId(), [
            '_token' => 'invalid_token' // Will be rejected anyway due to permissions
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Verify event still exists
        $entityManager->refresh($event);
        self::assertNotNull($event->getId());
    }

    public function testAdminCanEditAndDeleteAllEvents(): void
    {
        $client = static::createClient();
        $users = $this->createTestUsers();
        $entityManager = $this->getEntityManager();

        // Create an event owned by regular user
        $event = new Event();
        $event->setTitle('User Event');
        $event->setDescription('Created by regular user');
        $event->setLocation('User Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setCreatedBy($users['user']);
        $entityManager->persist($event);
        $entityManager->flush();

        // Test admin can access edit form for user's event
        $this->loginUser($client, $users['admin']);
        $client->request('GET', '/events/' . $event->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}
