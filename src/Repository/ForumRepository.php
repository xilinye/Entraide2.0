<?php

namespace App\Repository;

use App\Entity\{Forum, Category};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function searchByQueryAndCategory(?string $query = null, ?Category $category = null)
    {
        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');

        if ($query) {
            $qb->andWhere('f.title LIKE :query OR f.content LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($category) {
            $qb->andWhere('f.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }
}
