<?php

namespace App\Tests\Repository;

use App\Entity\{User, Skill, Category};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->truncateTables();
        $this->userRepository = $this->em->getRepository(User::class);
    }

    private function truncateTables(): void
    {
        $connection = $this->em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTables();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $connection->executeStatement('TRUNCATE ' . $table->getName());
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->clear();
    }

    public function testFindExpiredUnverifiedUsers(): void
    {
        $expiredUser = new User();
        $expiredUser->setPseudo('expired_user_' . uniqid());
        $expiredUser->setEmail('expired_' . uniqid() . '@test.com');
        $expiredUser->setPassword('password');
        $expiredUser->setIsVerified(false);
        $expiredUser->setTokenExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->em->persist($expiredUser);

        $nonExpiredUser = new User();
        $nonExpiredUser->setPseudo('non_expired_user_' . uniqid());
        $nonExpiredUser->setEmail('non_expired_' . uniqid() . '@test.com');
        $nonExpiredUser->setPassword('password');
        $nonExpiredUser->setIsVerified(false);
        $nonExpiredUser->setTokenExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->em->persist($nonExpiredUser);

        $this->em->flush();

        $result = $this->userRepository->findExpiredUnverifiedUsers();

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]->isVerified());
        $this->assertTrue($result[0]->isTokenExpired());
    }

    public function testFindBySkillsAndCategory(): void
    {
        $category = new Category();
        $category->setName('Test Category ' . uniqid());
        $this->em->persist($category);

        $skill = new Skill();
        $skill->setName('Test Skill ' . uniqid());
        $skill->setCategory($category);
        $this->em->persist($skill);

        $user = new User();
        $user->setPseudo('test_user_' . uniqid());
        $user->setEmail('test_' . uniqid() . '@test.com');
        $user->setPassword('password');
        $user->addSkill($skill);
        $this->em->persist($user);

        $this->em->flush();

        $result = $this->userRepository->findBySkillsAndCategory($skill, null);
        $this->assertCount(1, $result);

        $result = $this->userRepository->findBySkillsAndCategory(null, $category);
        $this->assertCount(1, $result);

        $otherCategory = new Category();
        $otherCategory->setName('Other Category ' . uniqid());
        $this->em->persist($otherCategory);
        $this->em->flush();

        $result = $this->userRepository->findBySkillsAndCategory(null, $otherCategory);
        $this->assertCount(0, $result);
    }

    public function testFindRecent(): void
    {
        $user1 = new User();
        $user1->setPseudo('user1_' . uniqid());
        $user1->setEmail('user1_' . uniqid() . '@test.com');
        $user1->setPassword('password');
        $user1->setCreatedAt(new \DateTimeImmutable('-3 days'));
        $this->em->persist($user1);

        $user2 = new User();
        $user2->setPseudo('user2_' . uniqid());
        $user2->setEmail('user2_' . uniqid() . '@test.com');
        $user2->setPassword('password');
        $user2->setCreatedAt(new \DateTimeImmutable('-2 days'));
        $this->em->persist($user2);

        $user3 = new User();
        $user3->setPseudo('user3_' . uniqid());
        $user3->setEmail('user3_' . uniqid() . '@test.com');
        $user3->setPassword('password');
        $user3->setCreatedAt(new \DateTimeImmutable('-1 day'));
        $this->em->persist($user3);

        $deletedUser = new User();
        $deletedUser->setPseudo('deleted_' . uniqid());
        $deletedUser->setEmail('deleted_' . uniqid() . '@test.com');
        $deletedUser->setPassword('password');
        $deletedUser->setDeletedAt(new \DateTimeImmutable());
        $this->em->persist($deletedUser);

        $anonymousUser = new User();
        $anonymousUser->setPseudo('anonymous_' . uniqid());
        $anonymousUser->setEmail('anonymous_' . uniqid() . '@test.com');
        $anonymousUser->setPassword('password');
        $anonymousUser->setRoles(['ROLE_ANONYMOUS']);
        $this->em->persist($anonymousUser);

        $this->em->flush();
        $this->em->clear();

        $result = $this->userRepository->findRecent(2);

        $this->assertCount(2, $result);
        $this->assertTrue(
            $result[0]->getCreatedAt() > $result[1]->getCreatedAt(),
            'Users are not ordered correctly by createdAt DESC'
        );
    }

    public function testPaginate(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setPseudo("user{$i}_" . uniqid());
            $user->setEmail("user{$i}_" . uniqid() . "@test.com");
            $user->setPassword('password');
            $this->em->persist($user);
        }
        $this->em->flush();

        $resultPage1 = $this->userRepository->paginate(1, 5);
        $this->assertCount(5, $resultPage1);

        $resultPage2 = $this->userRepository->paginate(2, 5);
        $this->assertCount(5, $resultPage2);

        $userIdsPage1 = array_map(fn($u) => $u->getId(), $resultPage1);
        $userIdsPage2 = array_map(fn($u) => $u->getId(), $resultPage2);
        $this->assertEmpty(array_intersect($userIdsPage1, $userIdsPage2));
    }

    public function testRemove(): void
    {
        $user = new User();
        $user->setPseudo('testuser' . uniqid());
        $user->setEmail('test' . uniqid() . '@test.com');
        $user->setPassword('password');
        $this->em->persist($user);
        $this->em->flush();

        $userId = $user->getId();
        $this->assertNotNull($userId);

        $this->userRepository->remove($user, true);

        $this->em->clear();
        $removedUser = $this->userRepository->find($userId);
        $this->assertNull($removedUser);
    }

    public function testFindByFilters(): void
    {
        $category = new Category();
        $category->setName('Test Category' . uniqid());
        $this->em->persist($category);

        $skill = new Skill();
        $skill->setName('Test Skill' . uniqid());
        $skill->setCategory($category);
        $this->em->persist($skill);

        $excludedUser = new User();
        $excludedUser->setPseudo('excluded' . uniqid());
        $excludedUser->setEmail('excluded' . uniqid() . '@test.com');
        $excludedUser->setPassword('password');
        $excludedUser->setIsVerified(true);
        $this->em->persist($excludedUser);

        $userWithSkill = new User();
        $userWithSkill->setPseudo('user1' . uniqid());
        $userWithSkill->setEmail('user1' . uniqid() . '@test.com');
        $userWithSkill->setPassword('password');
        $userWithSkill->addSkill($skill);
        $userWithSkill->setIsVerified(true);
        $this->em->persist($userWithSkill);

        $this->em->flush();
        $result = $this->userRepository->findByFilters($category, $skill, $excludedUser);
        $this->assertCount(1, $result);
        $this->assertEquals($userWithSkill->getId(), $result[0]->getId());
    }

    public function testFindOrCreateAnonymousUser(): void
    {
        $existing = $this->userRepository->findBy(['email' => 'anonymous@example.com']);
        foreach ($existing as $user) {
            $this->em->remove($user);
        }
        $this->em->flush();

        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();
        $this->assertSame('anonymous@example.com', $anonymousUser->getEmail());

        $anonymousUser2 = $this->userRepository->findOrCreateAnonymousUser();
        $this->assertSame($anonymousUser->getId(), $anonymousUser2->getId());
    }

    public function testFindAnonymousUser(): void
    {
        $email = 'anonymous@example.com';

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $anonymousUser = new User();
        $anonymousUser->setPseudo('anonymous' . uniqid());
        $anonymousUser->setEmail($email);
        $anonymousUser->setPassword('password');
        $anonymousUser->setRoles(['ROLE_ANONYMOUS']);
        $this->em->persist($anonymousUser);
        $this->em->flush();

        $result = $this->userRepository->findAnonymousUser();
        $this->assertSame($email, $result->getEmail());
    }

    public function testFindAllNonAnonymous(): void
    {
        $user = new User();
        $user->setPseudo('user' . uniqid());
        $user->setEmail('user' . uniqid() . '@test.com');
        $user->setPassword('password');
        $user->setIsVerified(true);
        $this->em->persist($user);

        $anonymousUser = new User();
        $anonymousUser->setPseudo('anonymous' . uniqid());
        $anonymousUser->setEmail('anonymous' . uniqid() . '@test.com');
        $anonymousUser->setPassword('password');
        $anonymousUser->setRoles(['ROLE_ANONYMOUS']);
        $this->em->persist($anonymousUser);

        $this->em->flush();

        $result = $this->userRepository->findAllNonAnonymous();
        $this->assertCount(1, $result);
        $this->assertNotContains('ROLE_ANONYMOUS', $result[0]->getRoles()); // Check roles
    }

    public function testCountActiveUsers(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setPseudo("user{$i}_" . uniqid());
            $user->setEmail("user{$i}_" . uniqid() . "@test.com");
            $user->setPassword('password');
            $this->em->persist($user);
        }

        $anonymousUser = new User();
        $anonymousUser->setPseudo('anonymous_' . uniqid());
        $anonymousUser->setEmail('anonymous@example.com');
        $anonymousUser->setPassword('password');
        $anonymousUser->setRoles(['ROLE_ANONYMOUS']);
        $this->em->persist($anonymousUser);

        $this->em->flush();

        $this->assertEquals(5, $this->userRepository->countActiveUsers());
    }
    public function testLoadUserByIdentifier(): void
    {
        $pseudo = 'testuser_' . uniqid();
        $email = 'test_' . uniqid() . '@test.com';

        $user = new User();
        $user->setPseudo($pseudo);
        $user->setEmail($email);
        $user->setPassword('password');
        $this->em->persist($user);
        $this->em->flush();

        $result = $this->userRepository->loadUserByIdentifier($pseudo);
        $this->assertSame($pseudo, $result->getPseudo());

        $result = $this->userRepository->loadUserByIdentifier($email);
        $this->assertSame($email, $result->getEmail());
    }
}
