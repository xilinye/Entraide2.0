<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.organizer', 'o')
            ->addSelect('o')
            ->where('e.startDate > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findPast(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.startDate <= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
