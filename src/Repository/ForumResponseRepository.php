<?php

namespace App\Repository;

use App\Entity\{ForumResponse, Forum};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ForumResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumResponse::class);
    }

    public function getTopForumResponses(Forum $forum, int $limit = 3): array
    {
        return $this->createQueryBuilder('fr')
            ->select('fr', 'AVG(r.score) as average')
            ->leftJoin('fr.ratings', 'r')
            ->where('fr.forum = :forum')
            ->setParameter('forum', $forum)
            ->groupBy('fr.id')
            ->orderBy('average', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
