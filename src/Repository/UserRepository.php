<?php

namespace App\Repository;

use App\Entity\{User, Skill, Category};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, PasswordUpgraderInterface};
use Doctrine\ORM\EntityManagerInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $em)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    public function findExpiredUnverifiedUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isVerified = false')
            ->andWhere('u.tokenExpiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function findBySkillsAndCategory(?Skill $skill, ?Category $category): array
    {
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.skills', 's');

        if ($skill) {
            $qb->andWhere('s = :skill')
                ->setParameter('skill', $skill);
        }

        if ($category) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRecent(int $maxResults): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    public function paginate(int $page, int $limit): array
    {
        return $this->createQueryBuilder('u')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function remove(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->remove($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFilters(?Category $category, ?Skill $skill, User $excludedUser): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u != :user')
            ->andWhere('u.roles NOT LIKE :anonymousRole')
            ->andWhere('u.isVerified = :isVerified')
            ->setParameter('user', $excludedUser)
            ->setParameter('anonymousRole', '%ROLE_ANONYMOUS%')
            ->setParameter('isVerified', true);

        if ($category || $skill) {
            $qb->innerJoin('u.skills', 's');

            if ($category) {
                $qb->andWhere('s.category = :category')
                    ->setParameter('category', $category);
            }

            if ($skill) {
                $qb->andWhere('s = :skill')
                    ->setParameter('skill', $skill);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOrCreateAnonymousUser(): User
    {
        $anonymousUser = $this->findOneBy(['email' => 'anonymous@example.com']);

        if (!$anonymousUser) {
            $anonymousUser = new User();
            $anonymousUser->setPseudo('Utilisateur Supprimé');
            $anonymousUser->setEmail('anonymous@example.com');
            $anonymousUser->setPassword(bin2hex(random_bytes(16)));
            $anonymousUser->setIsVerified(true);
            $anonymousUser->setRoles(['ROLE_ANONYMOUS']);
            $this->em->persist($anonymousUser);
            $this->em->flush();
        }

        return $anonymousUser;
    }

    public function findAnonymousUser(): ?User
    {
        return $this->findOneBy(['email' => 'anonymous@example.com']);
    }

    public function findAllNonAnonymous(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :role')
            ->setParameter('role', '%ROLE_ANONYMOUS%')
            ->getQuery()
            ->getResult();
    }
}
