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
        $user = (new User())
            ->setPseudo('valid_pseudo')
            ->setEmail('valid@example.com')
            ->setPassword('ValidPassword123!');

        // Définir l'ID avec réflexion
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, 1);

        $otherUser = (new User())
            ->setPseudo('other_valid')
            ->setEmail('other@example.com')
            ->setPassword('ValidPassword456!');

        // Définir un ID différent pour otherUser
        $otherUserReflection = new \ReflectionProperty(User::class, 'id');
        $otherUserReflection->setAccessible(true);
        $otherUserReflection->setValue($otherUser, 2);

        return (new ConversationDeletion())
            ->setUser($user)
            ->setOtherUser($otherUser)
            ->setConversationTitle('Titre valide');
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

        $errors = $this->validate($deletion);
        $this->assertCount(2, $errors);
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
        $invalid = new ConversationDeletion();
        $invalid->setConversationTitle('');

        $errors = $this->validate($invalid);

        $expected = [
            'L\'utilisateur est obligatoire.',
            'L\'autre utilisateur est obligatoire.',
            'Le titre ne peut pas être vide',
            'Le titre doit contenir au moins 2 caractères'
        ];

        $this->assertValidationMessages($errors, $expected);
    }

    public function testGetConversationTitleReturnsCorrectValue()
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle('Réunion équipe');
        $this->assertEquals('Réunion équipe', $deletion->getConversationTitle());
    }

    public function testConversationTitleMaxLengthExceeded()
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle(str_repeat('a', 256));

        $errors = $this->validate($deletion);
        $this->assertCount(1, $errors);
    }

    public function testSameUserInstanceValidation()
    {
        $user = new User();
        $user->setId(1);
        $deletion = (new ConversationDeletion())
            ->setUser($user)
            ->setOtherUser($user)
            ->setConversationTitle('Test');

        $errors = $this->validate($deletion);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString(
            'ne peut pas être avec soi-même',
            $errors[0]->getMessage()
        );
    }

    public function testInvalidFieldTypes()
    {
        $deletion = new ConversationDeletion();

        // Test avec des types invalides (doit échouer avant la validation)
        $this->expectException(\TypeError::class);
        $invalidUser = (object)['invalid' => 'type']; // Création d'un objet non-User
        $deletion->setUser($invalidUser);
    }

    public function testUserRelationshipLifecycle()
    {
        $user = new User();
        $deletion = new ConversationDeletion();

        // Ajout
        $user->addConversationDeletion($deletion);
        $this->assertCount(1, $user->getConversationDeletions());

        // Suppression
        $user->removeConversationDeletion($deletion);
        $this->assertCount(0, $user->getConversationDeletions());
    }

    public function testConversationTitleMinLength()
    {
        $deletion = $this->createValidDeletion();
        $deletion->setConversationTitle('A');

        $errors = $this->validate($deletion);
        $this->assertCount(1, $errors);
    }

    private function validate($entity): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return iterator_to_array($validator->validate($entity));
    }

    private function assertValidationMessages($errors, array $expected)
    {
        $messages = array_map(fn($e) => $e->getMessage(), $errors);

        foreach ($expected as $message) {
            $this->assertContains($message, $messages);
        }
    }
}
