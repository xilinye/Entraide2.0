<?php

namespace App\Tests\Controller;

use App\Entity\{Event, User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }
        $this->entityManager->close();
        parent::tearDown();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('user') . '@example.com')
            ->setPseudo(uniqid('user'))
            ->setPassword('$2y$13$IXJxGMI6W8hqHqe1.Config');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createEvent(User $organizer, bool $past = false): Event
    {
        $event = new Event();
        $event->setTitle(uniqid('Event'))
            ->setDescription('Test Description')
            ->setStartDate(new \DateTime($past ? '-2 days' : '+2 days'))
            ->setEndDate(new \DateTime($past ? '-1 day' : '+3 days'))
            ->setLocation('Test Location')
            ->setMaxAttendees(10)
            ->setOrganizer($organizer);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function testIndex(): void
    {
        $user = $this->createUser();
        $this->createEvent($user);
        $this->createEvent($user, true);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/evenement/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('À venir', $crawler->filter('h2')->eq(0)->text());
        $this->assertStringContainsString('Passés', $crawler->filter('h2')->eq(1)->text());
    }

    public function testNewEvent(): void
    {
        $organizer = $this->createUser();
        $this->client->loginUser($organizer);

        $this->client->request('GET', '/evenement/nouveau');
        $this->assertResponseIsSuccessful();
    }

    public function testShowEvent(): void
    {
        $organizer = $this->createUser();
        $event = $this->createEvent($organizer, true);

        $user = $this->createUser();
        $event->addAttendee($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/evenement/' . $event->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.rating-form');
    }

    public function testEditEvent(): void
    {
        $organizer = $this->createUser();
        $event = $this->createEvent($organizer);

        $this->client->loginUser($organizer);
        $crawler = $this->client->request('GET', '/evenement/' . $event->getId() . '/edit');

        $form = $crawler->selectButton('Mettre à jour')->form([
            'event[title]' => 'Updated Title'
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testEditEventForbidden(): void
    {
        $organizer = $this->createUser();
        $event = $this->createEvent($organizer);

        $user = $this->createUser();
        $this->client->loginUser($user);
        $this->client->request('GET', '/evenement/' . $event->getId() . '/edit');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegister(): void
    {
        $organizer = $this->createUser();
        $event = $this->createEvent($organizer);
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request('POST', '/evenement/' . $event->getId() . '/inscription');

        $this->assertResponseRedirects();
    }
}
