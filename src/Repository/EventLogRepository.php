<?php

namespace App\Repository;

use App\Entity\EventLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventLog>
 */
class EventLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventLog::class);
    }

    public function save(EventLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EventLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Log an event
     */
    public function logEvent(
        string $eventType,
        array $payload = [],
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): EventLog {
        $eventLog = new EventLog();
        $eventLog->setEventType($eventType)
                 ->setPayload($payload)
                 ->setUser($user)
                 ->setIpAddress($ipAddress)
                 ->setUserAgent($userAgent);

        $this->save($eventLog, true);

        return $eventLog;
    }

    /**
     * Find logs by event type
     */
    public function findByEventType(string $eventType, int $limit = 100): array
    {
        return $this->createQueryBuilder('el')
            ->where('el.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('el.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by user
     */
    public function findByUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('el')
            ->where('el.user = :user')
            ->setParameter('user', $user)
            ->orderBy('el.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent logs
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('el')
            ->orderBy('el.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs in date range
     */
    public function findByDateRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?string $eventType = null
    ): array {
        $qb = $this->createQueryBuilder('el')
            ->where('el.createdAt >= :startDate')
            ->andWhere('el.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('el.createdAt', 'DESC');

        if ($eventType) {
            $qb->andWhere('el.eventType = :eventType')
               ->setParameter('eventType', $eventType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get event statistics
     */
    public function getEventStats(?int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");
        
        $qb = $this->createQueryBuilder('el')
            ->select('el.eventType, COUNT(el.id) as count')
            ->where('el.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('el.eventType')
            ->orderBy('count', 'DESC');

        $results = $qb->getQuery()->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['eventType']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Get user activity stats
     */
    public function getUserActivityStats(User $user, ?int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");
        
        $qb = $this->createQueryBuilder('el')
            ->select('el.eventType, COUNT(el.id) as count')
            ->where('el.user = :user')
            ->andWhere('el.createdAt >= :startDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->groupBy('el.eventType')
            ->orderBy('count', 'DESC');

        $results = $qb->getQuery()->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['eventType']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");
        
        return $this->createQueryBuilder('el')
            ->delete()
            ->where('el.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
