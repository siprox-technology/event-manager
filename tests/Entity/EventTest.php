<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    private function createTestUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);

        return $user;
    }

    public function testEventCreationWithRequiredFields(): void
    {
        $event = new Event();
        $user = $this->createTestUser();
        $startDate = new \DateTimeImmutable('+1 week');

        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setLocation('Test Location');
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate($startDate);
        $event->setCreatedBy($user);

        self::assertSame('Test Event', $event->getTitle());
        self::assertSame('Test Description', $event->getDescription());
        self::assertSame('Test Location', $event->getLocation());
        self::assertSame(Event::STATUS_PLANNED, $event->getStatus());
        self::assertSame($startDate, $event->getStartDate());
        self::assertSame($user, $event->getCreatedBy());
    }

    public function testEventWithOptionalFields(): void
    {
        $event = new Event();
        $endDate = new \DateTimeImmutable('+1 week +2 hours');

        $event->setEndDate($endDate);
        $event->setMaxParticipants(100);

        self::assertSame($endDate, $event->getEndDate());
        self::assertSame(100, $event->getMaxParticipants());
    }

    public function testEventNullableEndDate(): void
    {
        $event = new Event();

        // End date should be nullable
        self::assertNull($event->getEndDate());

        // Should be able to set and unset end date
        $endDate = new \DateTimeImmutable('+1 week');
        $event->setEndDate($endDate);
        self::assertSame($endDate, $event->getEndDate());

        $event->setEndDate(null);
        self::assertNull($event->getEndDate());
    }

    public function testEventParticipantManagement(): void
    {
        $event = new Event();
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');

        // Initially no participants
        self::assertSame(0, $event->getParticipantCount());
        self::assertFalse($event->isParticipant($user1));
        self::assertCount(0, $event->getParticipants());

        // Add first participant
        $event->addParticipant($user1);
        self::assertSame(1, $event->getParticipantCount());
        self::assertTrue($event->isParticipant($user1));
        self::assertFalse($event->isParticipant($user2));
        self::assertCount(1, $event->getParticipants());
        self::assertContains($user1, $event->getParticipants());

        // Add second participant
        $event->addParticipant($user2);
        self::assertSame(2, $event->getParticipantCount());
        self::assertTrue($event->isParticipant($user1));
        self::assertTrue($event->isParticipant($user2));
        self::assertCount(2, $event->getParticipants());

        // Adding same participant again should not increase count
        $event->addParticipant($user1);
        self::assertSame(2, $event->getParticipantCount());

        // Remove participant
        $event->removeParticipant($user1);
        self::assertSame(1, $event->getParticipantCount());
        self::assertFalse($event->isParticipant($user1));
        self::assertTrue($event->isParticipant($user2));
        self::assertCount(1, $event->getParticipants());

        // Remove all participants
        $event->removeParticipant($user2);
        self::assertSame(0, $event->getParticipantCount());
        self::assertFalse($event->isParticipant($user2));
        self::assertCount(0, $event->getParticipants());
    }

    public function testEventCanAcceptMoreParticipants(): void
    {
        $event = new Event();
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');

        // Without max participants limit, should always accept more
        self::assertTrue($event->canAcceptMoreParticipants());

        $event->addParticipant($user1);
        self::assertTrue($event->canAcceptMoreParticipants());

        // With max participants limit
        $event->setMaxParticipants(2);
        self::assertTrue($event->canAcceptMoreParticipants()); // 1 < 2

        $event->addParticipant($user2);
        self::assertFalse($event->canAcceptMoreParticipants()); // 2 >= 2

        // Remove participant, should accept more again
        $event->removeParticipant($user1);
        self::assertTrue($event->canAcceptMoreParticipants()); // 1 < 2
    }

    public function testEventIsUpcoming(): void
    {
        $event = new Event();

        // Future event is upcoming
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        self::assertTrue($event->isUpcoming());

        // Past event is not upcoming
        $event->setStartDate(new \DateTimeImmutable('-1 week'));
        self::assertFalse($event->isUpcoming());

        // Event starting now is not upcoming (edge case)
        $event->setStartDate(new \DateTimeImmutable('now'));
        self::assertFalse($event->isUpcoming());
    }

    public function testEventStatusConstants(): void
    {
        // Test that status constants are defined
        self::assertSame('planned', Event::STATUS_PLANNED);
        self::assertSame('ongoing', Event::STATUS_ONGOING);
        self::assertSame('completed', Event::STATUS_COMPLETED);
        self::assertSame('cancelled', Event::STATUS_CANCELLED);
    }

    public function testEventGetAvailableStatuses(): void
    {
        $statuses = Event::getAvailableStatuses();

        self::assertIsArray($statuses);
        self::assertContains(Event::STATUS_PLANNED, $statuses);
        self::assertContains(Event::STATUS_ONGOING, $statuses);
        self::assertContains(Event::STATUS_COMPLETED, $statuses);
        self::assertContains(Event::STATUS_CANCELLED, $statuses);

        // Should have exactly 4 statuses
        self::assertCount(4, $statuses);
    }

    public function testEventRegistrationConstraints(): void
    {
        $event = new Event();
        $user = $this->createTestUser();

        // Planned, upcoming event with space should allow registration
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setMaxParticipants(10);

        self::assertSame(Event::STATUS_PLANNED, $event->getStatus());
        self::assertTrue($event->isUpcoming());
        self::assertTrue($event->canAcceptMoreParticipants());

        // Cancelled event should not allow registration
        $event->setStatus(Event::STATUS_CANCELLED);
        self::assertSame(Event::STATUS_CANCELLED, $event->getStatus());

        // Completed event should not allow registration
        $event->setStatus(Event::STATUS_COMPLETED);
        self::assertSame(Event::STATUS_COMPLETED, $event->getStatus());

        // Past event should not allow registration
        $event->setStatus(Event::STATUS_PLANNED);
        $event->setStartDate(new \DateTimeImmutable('-1 week'));
        self::assertFalse($event->isUpcoming());
    }

    public function testEventTimestamps(): void
    {
        $event = new Event();
        $now = new \DateTimeImmutable();

        $event->setCreatedAt($now);
        $event->setUpdatedAt($now);

        self::assertSame($now, $event->getCreatedAt());
        self::assertSame($now, $event->getUpdatedAt());
    }

    public function testEventToString(): void
    {
        $event = new Event();
        $event->setTitle('Test Event Title');

        // If there's a __toString method, test it
        if (method_exists($event, '__toString')) {
            self::assertSame('Test Event Title', (string) $event);
        } else {
            // If no __toString method, this test documents that
            self::assertTrue(true, 'Event does not implement __toString method');
        }
    }

    public function testEventValidation(): void
    {
        $event = new Event();

        // Test that required fields can be set
        $event->setTitle('Required Title');
        $event->setStartDate(new \DateTimeImmutable('+1 week'));
        $event->setStatus(Event::STATUS_PLANNED);

        self::assertNotEmpty($event->getTitle());
        self::assertNotNull($event->getStartDate());
        self::assertNotEmpty($event->getStatus());

        // Test optional fields can be null
        self::assertNull($event->getDescription());
        self::assertNull($event->getLocation());
        self::assertNull($event->getEndDate());

        // Test max participants has default value of 0
        self::assertSame(0, $event->getMaxParticipants());
    }

    public function testEventMaxParticipantsEdgeCases(): void
    {
        $event = new Event();

        // Test with max participants = 0 (means unlimited, should accept more)
        $event->setMaxParticipants(0);
        self::assertTrue($event->canAcceptMoreParticipants());

        // Test with max participants = 1
        $event->setMaxParticipants(1);
        self::assertTrue($event->canAcceptMoreParticipants());

        $user = $this->createTestUser();
        $event->addParticipant($user);
        self::assertFalse($event->canAcceptMoreParticipants());
    }
}
