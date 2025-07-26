<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function save(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find events with optional filtering and sorting
     */
    public function findWithFilters(
        ?string $status = null,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        ?string $location = null,
        ?string $search = null,
        string $orderBy = 'startDate',
        string $order = 'ASC',
        int $limit = null,
        int $offset = null
    ): array {
        $qb = $this->createFilteredQueryBuilder($status, $startDate, $endDate, $location, $search);
        
        $qb->orderBy('e.' . $orderBy, $order);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count events with filters
     */
    public function countWithFilters(
        ?string $status = null,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        ?string $location = null,
        ?string $search = null
    ): int {
        $qb = $this->createFilteredQueryBuilder($status, $startDate, $endDate, $location, $search);
        $qb->select('COUNT(e.id)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find upcoming events
     */
    public function findUpcoming(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.startDate > :now')
            ->andWhere('e.status != :cancelled')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('cancelled', Event::STATUS_CANCELLED)
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by participant
     */
    public function findByParticipant(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.participants', 'p')
            ->where('p = :user')
            ->setParameter('user', $user)
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events created by user
     */
    public function findByCreator(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events that need reminder notifications
     */
    public function findEventsNeedingReminder(\DateTimeImmutable $reminderThreshold): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.startDate <= :threshold')
            ->andWhere('e.startDate > :now')
            ->andWhere('e.status = :planned')
            ->setParameter('threshold', $reminderThreshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('planned', Event::STATUS_PLANNED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get events statistics
     */
    public function getEventStats(): array
    {
        return [
            'total' => $this->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'upcoming' => $this->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.startDate > :now')
                ->setParameter('now', new \DateTimeImmutable())
                ->getQuery()
                ->getSingleScalarResult(),
            'ongoing' => $this->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.status = :ongoing')
                ->setParameter('ongoing', Event::STATUS_ONGOING)
                ->getQuery()
                ->getSingleScalarResult(),
            'completed' => $this->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.status = :completed')
                ->setParameter('completed', Event::STATUS_COMPLETED)
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    private function createFilteredQueryBuilder(
        ?string $status = null,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        ?string $location = null,
        ?string $search = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('e');

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        if ($startDate) {
            $qb->andWhere('e.startDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('e.endDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($location) {
            $qb->andWhere('e.location LIKE :location')
               ->setParameter('location', '%' . $location . '%');
        }

        if ($search) {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search2')
               ->setParameter('search', '%' . $search . '%')
               ->setParameter('search2', '%' . $search . '%');
        }

        return $qb;
    }
}
