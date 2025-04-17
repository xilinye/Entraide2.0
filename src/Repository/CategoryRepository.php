<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findAllOrdered()
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsByName(string $name): bool
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function remove(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->remove($category);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
