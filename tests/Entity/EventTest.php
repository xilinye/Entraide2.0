<?php

namespace App\Tests\Entity;

use App\Entity\{Event, User, Rating};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class EventTest extends TestCase
{
    private function createValidEvent(): Event
    {
        $organizer = new User();
        return (new Event())
            ->setTitle('Meetup Symfony')
            ->setDescription('Conférence sur Symfony')
            ->setStartDate(new \DateTime('+1 day'))
            ->setEndDate(new \DateTime('+2 days'))
            ->setLocation('Paris')
            ->setMaxAttendees(10)
            ->setOrganizer($organizer);
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

        // Test entité invalide (sans aucune propriété définie)
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
        $this->assertEquals('La date de fin doit être après la date de début', $errors[0]->getMessage());
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
        $this->assertStringContainsString('Le nombre de participants doit être au moins 1', $errors[0]->getMessage());
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

    public function testOrganizerIsNotNullValidation(): void
    {
        $event = $this->createValidEvent()->setOrganizer(null);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $errors = $validator->validate($event);

        $this->assertCount(1, $errors);
        $this->assertEquals('organizer', $errors[0]->getPropertyPath());
    }

    public function testStartDateInPastValidation(): void
    {
        $event = $this->createValidEvent()
            ->setStartDate(new \DateTime('-2 days'))
            ->setEndDate(new \DateTime('+1 day'));

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $errors = $validator->validate($event);

        $this->assertCount(1, $errors);
        $this->assertEquals(Assert\GreaterThan::TOO_LOW_ERROR, $errors[0]->getCode());
    }
    public function testMaxAttendeesZero(): void
    {
        $event = $this->createValidEvent()->setMaxAttendees(0);
        $this->assertTrue($event->canRegister(new User())); // Vérifie que la limite est désactivée
    }

    public function testCanRegisterWhenEventIsPast(): void
    {
        $event = $this->createValidEvent()
            ->setStartDate(new \DateTime('-2 days'))
            ->setEndDate(new \DateTime('-1 day'));

        $this->assertFalse($event->canRegister(new User()));
    }

    public function testRatingAssociation(): void
    {
        $event = $this->createValidEvent();
        $rating = new Rating();

        $event->addRating($rating);
        $this->assertSame($event, $rating->getEvent());
        $this->assertTrue($event->getRatings()->contains($rating));
    }

    public function testEmptySortedAttendees(): void
    {
        $event = $this->createValidEvent();
        $this->assertEmpty($event->getSortedAttendees());
    }
}
