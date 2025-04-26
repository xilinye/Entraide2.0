<?php

namespace App\Repository;

use App\Entity\{BlogPost, Event, ForumResponse, Rating};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function getAverageForTarget($target)
    {
        $qb = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as average, COUNT(r.id) as total');

        if ($target instanceof BlogPost) {
            $qb->where('r.blogPost = :target');
        } elseif ($target instanceof Event) {
            $qb->where('r.event = :target');
        } elseif ($target instanceof ForumResponse) {
            $qb->where('r.forumResponse = :target');
        }

        return $qb->setParameter('target', $target)
            ->getQuery()
            ->getSingleResult();
    }

    public function getAverageForBlogPost(BlogPost $blogPost): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as average')
            ->where('r.blogPost = :blogPost')
            ->setParameter('blogPost', $blogPost)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0;
    }

    public function getAverageForEvent(Event $event): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as average')
            ->where('r.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0;
    }

    public function getAverageForForumResponse(ForumResponse $forumResponse): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as average')
            ->where('r.forumResponse = :forumResponse')
            ->setParameter('forumResponse', $forumResponse)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0;
    }
}
