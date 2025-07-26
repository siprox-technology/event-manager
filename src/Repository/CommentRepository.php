<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function save(Comment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Comment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find comments for a post
     */
    public function findByPost(Post $post, bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.post = :post')
            ->setParameter('post', $post)
            ->orderBy('c.createdAt', 'ASC');

        if (!$includeHidden) {
            $qb->andWhere('c.isHidden = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find comments by author
     */
    public function findByAuthor(User $author): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.author = :author')
            ->setParameter('author', $author)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent comments
     */
    public function findRecent(int $limit = 10, bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!$includeHidden) {
            $qb->where('c.isHidden = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find replies to a comment
     */
    public function findReplies(Comment $comment): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->andWhere('c.isHidden = false')
            ->setParameter('parent', $comment)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count comments for a post
     */
    public function countByPost(Post $post, bool $includeHidden = false): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.post = :post')
            ->setParameter('post', $post);

        if (!$includeHidden) {
            $qb->andWhere('c.isHidden = false');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find moderation queue (hidden comments)
     */
    public function findModerationQueue(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isHidden = true')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get comment statistics
     */
    public function getCommentStats(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        return [
            'total' => $qb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult(),
            'published' => $qb->select('COUNT(c.id)')
                ->where('c.isHidden = false')
                ->getQuery()
                ->getSingleScalarResult(),
            'hidden' => $qb->select('COUNT(c.id)')
                ->where('c.isHidden = true')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
}
