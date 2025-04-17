<?php

namespace App\Repository;

use App\Entity\Skill;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skill::class);
    }

    public function findBySearchFilters(?string $query = null, ?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('s');

        if (!empty($query)) { // Gère les chaînes vides différemment de null
            $qb->andWhere('s.name LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($category) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithCategories()
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->addSelect('c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $maxResults): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    public function remove(Skill $skill, bool $flush = false): void
    {
        $this->getEntityManager()->remove($skill);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
