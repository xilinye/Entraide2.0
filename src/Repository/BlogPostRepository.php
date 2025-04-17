<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    public function findAllPublished()
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?BlogPost
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function search(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC');

        if ($query) {
            $qb->andWhere('LOWER(b.title) LIKE LOWER(:query)')
                ->setParameter('query', '%' . $query . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
