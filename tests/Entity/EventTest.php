<?php

namespace App\Tests\Entity;

use App\Entity\{Event, User};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class EventTest extends TestCase
{
    private function createValidEvent(): Event
    {
        return (new Event())
            ->setTitle('Meetup Symfony')
            ->setDescription('Conférence sur Symfony')
            ->setStartDate(new \DateTime('+1 day'))
            ->setEndDate(new \DateTime('+2 days'))
            ->setLocation('Paris')
            ->setMaxAttendees(10);
    }

    public function testGettersAndSetters(): void
    {
        $event = $this->createValidEvent();
        $user = new User();

        $event->setOrganizer($user);
        $this->assertSame($user, $event->getOrganizer());

        $this->assertEquals('Meetup Symfony', $event->getTitle());
        $this->assertEquals('Paris', $event->getLocation());
        $this->assertFalse($event->isPast());
    }

    public function testValidationConstraints(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        // Test entité valide
        $validEvent = $this->createValidEvent();
        $errors = $validator->validate($validEvent);
        $this->assertCount(0, $errors);

        // Test entité invalide
        $invalidEvent = new Event();
        $errors = $validator->validate($invalidEvent);
        $this->assertGreaterThan(0, count($errors));
    }

    public function testDateValidation(): void
    {
        $event = $this->createValidEvent()
            ->setStartDate(new \DateTime('+2 days'))
            ->setEndDate(new \DateTime('+1 day'));

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $errors = $validator->validate($event);
        $this->assertCount(1, $errors);
        $this->assertEquals('La date de fin doit être postérieure à la date de début', $errors[0]->getMessage());
    }

    public function testMaxAttendeesValidation(): void
    {
        $event = $this->createValidEvent()
            ->setMaxAttendees(-5);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $errors = $validator->validate($event);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Cette valeur doit être positive ou nulle.', $errors[0]->getMessage());
    }

    public function testCanRegister(): void
    {
        $event = $this->createValidEvent();
        $user = new User();
        $organizer = new User();

        $event->setOrganizer($organizer);

        // Test utilisateur non organisateur
        $this->assertTrue($event->canRegister($user));

        // Test utilisateur organisateur
        $this->assertFalse($event->canRegister($organizer));

        // Test utilisateur déjà inscrit
        $event->addAttendee($user);
        $this->assertFalse($event->canRegister($user));

        // Test événement complet
        $event->setMaxAttendees(1);
        $this->assertFalse($event->canRegister(new User()));
    }

    public function testIsPast(): void
    {
        $futureEvent = $this->createValidEvent();
        $this->assertFalse($futureEvent->isPast());

        $pastEvent = $this->createValidEvent()
            ->setStartDate(new \DateTime('-2 days'))
            ->setEndDate(new \DateTime('-1 day'));
        $this->assertTrue($pastEvent->isPast());
    }

    public function testAttendeeManagement(): void
    {
        $event = $this->createValidEvent();
        $user1 = new User();
        $user2 = new User();

        $event->addAttendee($user1);
        $event->addAttendee($user2);

        $this->assertCount(2, $event->getAttendees());
        $this->assertTrue($event->getAttendees()->contains($user1));

        $event->removeAttendee($user1);
        $this->assertCount(1, $event->getAttendees());
    }

    public function testSortedAttendees(): void
    {
        $event = $this->createValidEvent();

        $user1 = (new User())->setPseudo('Bob');
        $user2 = (new User())->setPseudo('Alice');

        $event->addAttendee($user1);
        $event->addAttendee($user2);

        $sorted = $event->getSortedAttendees();
        $this->assertEquals('Alice', $sorted[0]->getPseudo());
        $this->assertEquals('Bob', $sorted[1]->getPseudo());
    }

    public function testImageHandling(): void
    {
        $event = $this->createValidEvent();
        $event->setImageName('event.jpg');

        $this->assertEquals('event.jpg', $event->getImageName());
        $this->assertNull($event->getImageFile());
    }
}
