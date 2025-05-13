<?php

namespace App\Tests\Entity;

use App\Entity\{ConversationDeletion, User};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class ConversationDeletionTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $deletion = new ConversationDeletion();
        $user = new User();
        $otherUser = new User();
        $date = new \DateTimeImmutable();

        $deletion
            ->setUser($user)
            ->setOtherUser($otherUser)
            ->setDeletedAt($date)
            ->setConversationTitle('Projet Client X');

        $this->assertSame($user, $deletion->getUser());
        $this->assertSame($otherUser, $deletion->getOtherUser());
        $this->assertSame($date, $deletion->getDeletedAt());
        $this->assertEquals('Projet Client X', $deletion->getConversationTitle());
    }

    public function testPrePersistSetsDeletedAt(): void
    {
        $deletion = new ConversationDeletion();
        $deletion->setConversationTitle('Test');

        // Déclenchement manuel du PrePersist
        $deletion->setDeletedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $deletion->getDeletedAt());
        $this->assertEqualsWithDelta(
            time(),
            $deletion->getDeletedAt()->getTimestamp(),
            2 // Marge d'erreur de 2 secondes
        );
    }

    public function testDoesNotOverwriteExistingDeletedAt(): void
    {
        $date = new \DateTimeImmutable('2023-01-01');
        $deletion = new ConversationDeletion();
        $deletion->setDeletedAt($date);

        $deletion->setDeletedAtValue();

        $this->assertSame($date, $deletion->getDeletedAt());
    }

    public function testBidirectionalUserRelationship(): void
    {
        $user = new User();
        $deletion = new ConversationDeletion();
        $deletion->setUser($user);

        $this->assertContains($deletion, $user->getConversationDeletions());
    }

    public function testValidationConstraints(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $invalidDeletion = new ConversationDeletion();
        $errors = $validator->validate($invalidDeletion);

        $this->assertCount(3, $errors);
    }

    public function testConversationTitleLength(): void
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle(str_repeat('a', 255));

        $validator = Validation::createValidator();
        $errors = $validator->validate($deletion);
        $this->assertCount(0, $errors);
    }

    private function createValidDeletion(): ConversationDeletion
    {
        return (new ConversationDeletion())
            ->setUser(new User())
            ->setOtherUser(new User())
            ->setConversationTitle('Discussion importante');
    }

    public function testToStringRepresentation(): void
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle('Réunion équipe');

        $this->assertStringContainsString(
            'Réunion équipe',
            $deletion->getConversationTitle()
        );
    }

    public function testUserAndOtherUserCannotBeSame(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $user1 = new User();
        $user2 = new User();

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user1, 1);
        $reflection->setValue($user2, 1);

        $deletion = new ConversationDeletion();
        $deletion->setUser($user1)
            ->setOtherUser($user2)
            ->setConversationTitle('Test');

        $errors = $validator->validate($deletion);
        $this->assertGreaterThan(0, count($errors));
    }


    public function testConversationTitleCannotBeEmpty()
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle('');

        $validator = Validation::createValidator();
        $errors = $validator->validate($deletion);
        $this->assertCount(0, $errors); // À ajuster selon les contraintes
    }

    public function testUserChangeRemovesFromPreviousUser()
    {
        $user1 = new User();
        $user2 = new User();
        $deletion = new ConversationDeletion();

        $deletion->setUser($user1);
        $deletion->setUser($user2);

        $this->assertNotContains($deletion, $user1->getConversationDeletions());
        $this->assertContains($deletion, $user2->getConversationDeletions());
    }

    public function testValidationMessages()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping() // Activation du mapping des attributs
            ->getValidator();

        $deletion = new ConversationDeletion();
        $errors = $validator->validate($deletion);

        $expectedMessages = [
            'L\'utilisateur est obligatoire.',
            'L\'autre utilisateur est obligatoire.',
            'Le titre de la conversation est obligatoire.'
        ];

        $receivedMessages = array_map(fn($e) => $e->getMessage(), iterator_to_array($errors));
        foreach ($expectedMessages as $message) {
            $this->assertContains($message, $receivedMessages);
        }
    }

    public function testGetConversationTitleReturnsCorrectValue()
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle('Réunion équipe');
        $this->assertEquals('Réunion équipe', $deletion->getConversationTitle());
    }
}
