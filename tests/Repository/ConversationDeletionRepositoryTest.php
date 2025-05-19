<?php

namespace App\Tests\Repository;

use App\Entity\{ConversationDeletion, User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;


class ConversationDeletionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->validator = self::getContainer()->get(ValidatorInterface::class);

        // Désactiver les transactions pour éviter les conflits
        $this->entityManager->getConnection()->setAutoCommit(false);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Annuler la transaction et réactiver l'auto-commit
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->getConnection()->setAutoCommit(true);
        parent::tearDown();
    }

    // Teste la persistance et la récupération d'une ConversationDeletion
    public function testPersistAndRetrieve(): void
    {
        $user = $this->createUser('user@test.com');
        $otherUser = $this->createUser('other@test.com');

        $deletion = new ConversationDeletion();
        $deletion->setUser($user);
        $deletion->setOtherUser($otherUser);
        $deletion->setConversationTitle('Test Title');

        $this->entityManager->persist($deletion);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->getRepository(ConversationDeletion::class)->find($deletion->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals('Test Title', $retrieved->getConversationTitle());
        $this->assertEquals($user->getId(), $retrieved->getUser()->getId());
        $this->assertEquals($otherUser->getId(), $retrieved->getOtherUser()->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $retrieved->getDeletedAt());
    }

    // Valide la contrainte d'utilisateurs différents
    public function testValidationForSameUserAndOtherUser(): void
    {
        $user = $this->createUser('same@test.com');

        $deletion = new ConversationDeletion();
        $deletion->setUser($user);
        $deletion->setOtherUser($user);
        $deletion->setConversationTitle('Invalid');

        $violations = $this->validator->validate($deletion);
        $this->assertCount(1, $violations);
        $this->assertEquals('Une conversation ne peut pas être avec soi-même', $violations[0]->getMessage());
        $this->assertEquals('otherUser', $violations[0]->getPropertyPath());
    }

    // Teste la relation bidirectionnelle avec User
    public function testBidirectionalAssociation(): void
    {
        $user1 = $this->createUser('user1@bidir.com');
        $user2 = $this->createUser('user2@bidir.com');

        $deletion = new ConversationDeletion();
        $deletion->setUser($user1);
        $deletion->setOtherUser($user2);
        $deletion->setConversationTitle('Bidi Test');

        $this->entityManager->persist($deletion);
        $this->entityManager->flush();

        // Vérifie que user1 a la suppression
        $this->assertTrue($user1->getConversationDeletions()->contains($deletion));

        // Change l'utilisateur à user2
        $deletion->setUser($user2);
        $this->entityManager->flush();

        // Vérifie que user1 ne l'a plus et user2 l'a
        $this->assertFalse($user1->getConversationDeletions()->contains($deletion));
        $this->assertTrue($user2->getConversationDeletions()->contains($deletion));
    }

    // Crée un User avec un email unique
    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo('pseudo_' . $email);
        $user->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
}
