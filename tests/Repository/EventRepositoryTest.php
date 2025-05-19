<?php

namespace App\Tests\Repository;

use App\Entity\{Event, User};
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->em->getRepository(Event::class);
        $this->em->beginTransaction();
    }

    public function testFindUpcoming(): void
    {
        // Create organizer
        $organizer = new User();
        $organizer->setEmail('organizer@example.com');
        $organizer->setPseudo('organizer');
        $organizer->setPassword('password');
        $this->em->persist($organizer);

        // Create upcoming event
        $eventUpcoming = new Event();
        $eventUpcoming->setTitle('Upcoming Event');
        $eventUpcoming->setDescription('Test Description');
        $eventUpcoming->setStartDate(new \DateTime('+1 day'));
        $eventUpcoming->setEndDate(new \DateTime('+2 days'));
        $eventUpcoming->setLocation('Test Location');
        $eventUpcoming->setMaxAttendees(10);
        $eventUpcoming->setOrganizer($organizer);
        $this->em->persist($eventUpcoming);

        // Create past event
        $eventPast = new Event();
        $eventPast->setTitle('Past Event');
        $eventPast->setDescription('Test Description');
        $eventPast->setStartDate(new \DateTime('-1 day'));
        $eventPast->setEndDate(new \DateTime('-1 hour'));
        $eventPast->setLocation('Test Location');
        $eventPast->setMaxAttendees(10);
        $eventPast->setOrganizer($organizer);
        $this->em->persist($eventPast);

        $this->em->flush();
        $this->em->clear(); // Detach entities to simulate fresh fetch

        $upcomingEvents = $this->repository->findUpcoming();

        $this->assertCount(1, $upcomingEvents, 'Should find exactly one upcoming event.');
        $this->assertSame('Upcoming Event', $upcomingEvents[0]->getTitle());
        $this->assertSame('organizer', $upcomingEvents[0]->getOrganizer()->getPseudo());
    }

    public function testFindPast(): void
    {
        // Create organizer
        $organizer = new User();
        $organizer->setEmail('organizer@example.com');
        $organizer->setPseudo('organizer');
        $organizer->setPassword('password');
        $this->em->persist($organizer);

        // Create past events with different start dates
        $event1 = new Event();
        $event1->setTitle('Event 1');
        $event1->setDescription('Test Description');
        $event1->setStartDate(new \DateTime('-2 days'));
        $event1->setEndDate(new \DateTime('-1 day'));
        $event1->setLocation('Test Location');
        $event1->setMaxAttendees(10);
        $event1->setOrganizer($organizer);
        $this->em->persist($event1);

        $event2 = new Event();
        $event2->setTitle('Event 2');
        $event2->setDescription('Test Description');
        $event2->setStartDate(new \DateTime('-1 day'));
        $event2->setEndDate(new \DateTime('-1 hour'));
        $event2->setLocation('Test Location');
        $event2->setMaxAttendees(10);
        $event2->setOrganizer($organizer);
        $this->em->persist($event2);

        $this->em->flush();
        $this->em->clear(); // Detach entities

        $pastEvents = $this->repository->findPast();

        $this->assertCount(2, $pastEvents, 'Should find two past events.');
        $this->assertSame('Event 2', $pastEvents[0]->getTitle(), 'Most recent event should come first.');
        $this->assertSame('Event 1', $pastEvents[1]->getTitle());
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback(); // Rollback transaction to undo changes
        }
        parent::tearDown();
        $this->em->close();
        unset($this->em, $this->repository);
    }
}
