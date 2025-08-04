<?php

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EventControllerTest extends WebTestCase
{
    private function getEntityManager(): EntityManagerInterface
    {
        $kernel = self::bootKernel();
        return $kernel->getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (self::$kernel) {
            $entityManager = $this->getEntityManager();

            // Clean up test events
            $events = $entityManager->getRepository(Event::class)->findBy([
                'title' => [
                    'Future Tech Conference',
                    'Current Workshop',
                    'Past Seminar',
                    'Cancelled Meeting'
                ]
            ]);
            foreach ($events as $event) {
                $entityManager->remove($event);
            }

            // Clean up test users  
            $users = $entityManager->getRepository(User::class)->findBy([
                'email' => ['admin@events.test', 'user@events.test']
            ]);
            foreach ($users as $user) {
                $entityManager->remove($user);
            }

            $entityManager->flush();
            $entityManager->close();
        }
        parent::tearDown();
    }

    private function createTestUsersAndEvents(): array
    {
        $entityManager = $this->getEntityManager();

        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@events.test');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setPassword('hashed_password');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsActive(true);
        $admin->setIsEmailVerified(true);

        $entityManager->persist($admin);

        // Create regular user
        $user = new User();
        $user->setEmail('user@events.test');
        $user->setFirstName('Regular');
        $user->setLastName('User');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);

        $entityManager->persist($user);
        $entityManager->flush(); // Persist users first before creating events

        // Event 1: Planned future event
        $event1 = new Event();
        $event1->setTitle('Future Tech Conference');
        $event1->setDescription('A conference about future technologies');
        $event1->setLocation('Tech Center');
        $event1->setStatus(Event::STATUS_PLANNED);
        $event1->setStartDate(new \DateTimeImmutable('+1 week'));
        $event1->setEndDate(new \DateTimeImmutable('+1 week +2 hours'));
        $event1->setCreatedBy($admin);
        $event1->setMaxParticipants(100);

        $entityManager->persist($event1);

        // Event 2: Ongoing current event
        $event2 = new Event();
        $event2->setTitle('Current Workshop');
        $event2->setDescription('An ongoing workshop');
        $event2->setLocation('Workshop Room');
        $event2->setStatus(Event::STATUS_ONGOING);
        $event2->setStartDate(new \DateTimeImmutable('-1 hour'));
        $event2->setEndDate(new \DateTimeImmutable('+1 hour'));
        $event2->setCreatedBy($admin);
        $event2->setMaxParticipants(50);

        $entityManager->persist($event2);

        // Event 3: Completed past event
        $event3 = new Event();
        $event3->setTitle('Past Seminar');
        $event3->setDescription('A completed seminar');
        $event3->setLocation('Seminar Hall');
        $event3->setStatus(Event::STATUS_COMPLETED);
        $event3->setStartDate(new \DateTimeImmutable('-2 days'));
        $event3->setEndDate(new \DateTimeImmutable('-2 days +3 hours'));
        $event3->setCreatedBy($admin);
        $event3->setMaxParticipants(75);

        $entityManager->persist($event3);

        // Event 4: Cancelled event
        $event4 = new Event();
        $event4->setTitle('Cancelled Meeting');
        $event4->setDescription('A cancelled meeting');
        $event4->setLocation('Meeting Room');
        $event4->setStatus(Event::STATUS_CANCELLED);
        $event4->setStartDate(new \DateTimeImmutable('+3 days'));
        $event4->setEndDate(new \DateTimeImmutable('+3 days +1 hour'));
        $event4->setCreatedBy($admin);
        $event4->setMaxParticipants(20);

        $entityManager->persist($event4);
        $entityManager->flush();

        return [
            'admin' => $admin,
            'user' => $user,
            'future' => $event1,
            'ongoing' => $event2,
            'completed' => $event3,
            'cancelled' => $event4,
        ];
    }

    public function testEventIndexPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/events');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Events');
    }

    public function testEventIndexDisplaysEvents(): void
    {
        $client = static::createClient();
        $testData = $this->createTestUsersAndEvents();

        $crawler = $client->request('GET', '/events');

        self::assertResponseIsSuccessful();

        // Check that events are displayed
        self::assertStringContainsString('Future Tech Conference', $client->getResponse()->getContent());
        self::assertStringContainsString('Current Workshop', $client->getResponse()->getContent());
        self::assertStringContainsString('Past Seminar', $client->getResponse()->getContent());
    }

    public function testEventIndexFiltering(): void
    {
        $client = static::createClient();
        $testData = $this->createTestUsersAndEvents();

        // Filter by status
        $client->request('GET', '/events?status=' . Event::STATUS_PLANNED);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Future Tech Conference', $client->getResponse()->getContent());
        self::assertStringNotContainsString('Past Seminar', $client->getResponse()->getContent());

        // Filter by location
        $client->request('GET', '/events?location=Tech Center');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Future Tech Conference', $client->getResponse()->getContent());
        self::assertStringNotContainsString('Workshop Room', $client->getResponse()->getContent());

        // Search functionality
        $client->request('GET', '/events?search=Conference');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Future Tech Conference', $client->getResponse()->getContent());
        self::assertStringNotContainsString('Current Workshop', $client->getResponse()->getContent());
    }

    public function testEventIndexPagination(): void
    {
        $client = static::createClient();

        // Test first page
        $client->request('GET', '/events?page=1');
        self::assertResponseIsSuccessful();

        // Test invalid page defaults to 1
        $client->request('GET', '/events?page=0');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/events?page=-1');
        self::assertResponseIsSuccessful();
    }

    public function testEventShowPageDisplaysEventDetails(): void
    {
        $client = static::createClient();
        $testData = $this->createTestUsersAndEvents();
        $event = $testData['future'];

        $client->request('GET', '/events/' . $event->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($event->getTitle(), $client->getResponse()->getContent());
        self::assertStringContainsString($event->getDescription(), $client->getResponse()->getContent());
        self::assertStringContainsString($event->getLocation(), $client->getResponse()->getContent());
    }

    public function testEventShowPageWith404ForNonexistentEvent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/events/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpcomingEventsApiEndpoint(): void
    {
        $client = static::createClient();
        $testData = $this->createTestUsersAndEvents();

        $client->request('GET', '/events/upcoming');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($responseData);

        // Should contain upcoming events
        $eventTitles = array_column($responseData, 'title');
        self::assertContains('Future Tech Conference', $eventTitles);

        // Check event data structure
        if (!empty($responseData)) {
            $event = $responseData[0];
            self::assertArrayHasKey('id', $event);
            self::assertArrayHasKey('title', $event);
            self::assertArrayHasKey('description', $event);
            self::assertArrayHasKey('location', $event);
            self::assertArrayHasKey('startDate', $event);
            self::assertArrayHasKey('endDate', $event);
            self::assertArrayHasKey('status', $event);
            self::assertArrayHasKey('participantCount', $event);
            self::assertArrayHasKey('maxParticipants', $event);
            self::assertArrayHasKey('canAcceptMoreParticipants', $event);
        }
    }

    public function testEventAvailableStatusesAreAccessible(): void
    {
        $statuses = Event::getAvailableStatuses();

        self::assertIsArray($statuses);
        self::assertContains(Event::STATUS_PLANNED, $statuses);
        self::assertContains(Event::STATUS_ONGOING, $statuses);
        self::assertContains(Event::STATUS_COMPLETED, $statuses);
        self::assertContains(Event::STATUS_CANCELLED, $statuses);
    }

    public function testEventParticipantFunctionality(): void
    {
        $testData = $this->createTestUsersAndEvents();
        $event = $testData['future'];
        $user = $testData['user'];

        // Test participant count starts at 0
        self::assertSame(0, $event->getParticipantCount());

        // Test can accept more participants
        self::assertTrue($event->canAcceptMoreParticipants());

        // Test user is not initially a participant
        self::assertFalse($event->isParticipant($user));

        // Add participant
        $event->addParticipant($user);
        self::assertTrue($event->isParticipant($user));
        self::assertSame(1, $event->getParticipantCount());

        // Remove participant
        $event->removeParticipant($user);
        self::assertFalse($event->isParticipant($user));
        self::assertSame(0, $event->getParticipantCount());
    }

    public function testEventRegistrationConstraints(): void
    {
        $testData = $this->createTestUsersAndEvents();
        $event = $testData['future'];
        $user = $testData['user'];

        // Test planned event allows registration
        self::assertTrue($event->getStatus() === Event::STATUS_PLANNED);
        self::assertTrue($event->canAcceptMoreParticipants());
        self::assertTrue($event->isUpcoming());

        // Test completed event constraints
        $completedEvent = $testData['completed'];
        self::assertFalse($completedEvent->isUpcoming());

        // Test cancelled event constraints  
        $cancelledEvent = $testData['cancelled'];
        self::assertSame(Event::STATUS_CANCELLED, $cancelledEvent->getStatus());
    }

    public function testEventCRUDOperationsExist(): void
    {
        // This test verifies the basic structure exists
        // TODO: Implement when create/edit/delete functionality is added
        self::assertTrue(method_exists(Event::class, 'setTitle'));
        self::assertTrue(method_exists(Event::class, 'setDescription'));
        self::assertTrue(method_exists(Event::class, 'setLocation'));
        self::assertTrue(method_exists(Event::class, 'setStatus'));
        self::assertTrue(method_exists(Event::class, 'setStartDate'));
        self::assertTrue(method_exists(Event::class, 'setEndDate'));
    }
}
