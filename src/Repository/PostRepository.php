<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function save(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find published posts with optional filtering
     */
    public function findPublished(
        ?string $search = null,
        ?array $tags = null,
        ?User $author = null,
        string $orderBy = 'createdAt',
        string $order = 'DESC',
        int $limit = null,
        int $offset = null
    ): array {
        $qb = $this->createPublishedQueryBuilder($search, $tags, $author);
        
        $qb->orderBy('p.' . $orderBy, $order);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count published posts with filters
     */
    public function countPublished(
        ?string $search = null,
        ?array $tags = null,
        ?User $author = null
    ): int {
        $qb = $this->createPublishedQueryBuilder($search, $tags, $author);
        $qb->select('COUNT(p.id)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find post by slug
     */
    public function findBySlug(string $slug): ?Post
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.isPublished = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find recent posts
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = true')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find posts by author
     */
    public function findByAuthor(User $author, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.author = :author')
            ->setParameter('author', $author)
            ->orderBy('p.createdAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('p.isPublished = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find posts by tag
     */
    public function findByTag(string $tag): array
    {
        return $this->createQueryBuilder('p')
            ->where('JSON_CONTAINS(p.tags, :tag) = 1')
            ->andWhere('p.isPublished = true')
            ->setParameter('tag', '"' . $tag . '"')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all unique tags
     */
    public function getAllTags(): array
    {
        $posts = $this->createQueryBuilder('p')
            ->select('p.tags')
            ->where('p.isPublished = true')
            ->andWhere('p.tags IS NOT NULL')
            ->getQuery()
            ->getResult();

        $allTags = [];
        foreach ($posts as $post) {
            if (!empty($post['tags'])) {
                $allTags = array_merge($allTags, $post['tags']);
            }
        }

        return array_unique($allTags);
    }

    /**
     * Get popular posts (most commented)
     */
    public function findPopular(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.comments', 'c')
            ->where('p.isPublished = true')
            ->groupBy('p.id')
            ->orderBy('COUNT(c.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search posts
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = true')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query OR p.excerpt LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function createPublishedQueryBuilder(
        ?string $search = null,
        ?array $tags = null,
        ?User $author = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isPublished = true');

        if ($search) {
            $qb->andWhere('p.title LIKE :search OR p.content LIKE :search OR p.excerpt LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($tags && !empty($tags)) {
            foreach ($tags as $i => $tag) {
                $qb->andWhere("JSON_CONTAINS(p.tags, :tag{$i}) = 1")
                   ->setParameter("tag{$i}", '"' . $tag . '"');
            }
        }

        if ($author) {
            $qb->andWhere('p.author = :author')
               ->setParameter('author', $author);
        }

        return $qb;
    }
}
