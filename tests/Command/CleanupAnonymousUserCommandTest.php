<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\{User, Message, BlogPost, Rating, Event, Forum, ForumResponse};
use App\Repository\UserRepository;

class CleanupAnonymousUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private Application $application;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->userRepository = $this->em->getRepository(User::class);
        $this->application = new Application(self::$kernel);
        $this->em->beginTransaction(); // Début de transaction pour isolation
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback(); // Annulation des changements après chaque test
        }
        parent::tearDown();
    }

    private function createAnonymousUser(): User
    {
        $user = new User();
        $user->setPseudo('Anonymous');
        $user->setEmail('anonymous@example.com');
        $user->setPassword(bin2hex(random_bytes(16)));
        $user->setRoles(['ROLE_ANONYMOUS']);
        $user->setIsVerified(true);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    public function testNoAnonymousUserFound(): void
    {
        $command = $this->application->find('app:cleanup-anonymous-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Aucun utilisateur anonyme trouvé', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testAnonymousUserDeletedWhenNoRelations(): void
    {
        $user = $this->createAnonymousUser();

        $command = $this->application->find('app:cleanup-anonymous-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('supprimé avec succès', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        $this->assertNull($this->userRepository->find($user->getId()));
    }

    public function testSelfReferencingMessagesAreDeleted(): void
    {
        $user = $this->createAnonymousUser();

        $message = new Message();
        $message->setTitle('Test Title');
        $message->setSender($user);
        $message->setReceiver($user);
        $message->setContent('Test');
        $this->em->persist($message);
        $this->em->flush();

        $command = $this->application->find('app:cleanup-anonymous-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertNull($this->em->getRepository(Message::class)->find($message->getId()));
        $this->assertNull($this->userRepository->find($user->getId()));
    }

    public function testCommandFailsWhenOtherRelationsExist(): void
    {
        $user = $this->createAnonymousUser();

        $blogPost = new BlogPost();
        $blogPost->setTitle('Test');
        $blogPost->setContent('Content');
        $blogPost->setAuthor($user);
        $this->em->persist($blogPost);
        $this->em->flush();

        $command = $this->application->find('app:cleanup-anonymous-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertNotNull($this->userRepository->find($user->getId()));
    }

    public function testDryRunDoesNotPersistChanges(): void
    {
        $user = $this->createAnonymousUser();

        $message = new Message();
        $message->setTitle('Test Title');
        $message->setSender($user);
        $message->setReceiver($user);
        $message->setContent('Test');
        $this->em->persist($message);
        $this->em->flush();

        $command = $this->application->find('app:cleanup-anonymous-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dry-run' => true]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[DRY RUN]', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        $this->assertNotNull($this->em->getRepository(Message::class)->find($message->getId()));
        $this->assertNotNull($this->userRepository->find($user->getId()));
    }

    public function testCommandFailsWithForumPostRelation(): void
    {
        $user = $this->createAnonymousUser();

        $forumPost = new Forum();
        $forumPost->setTitle('Test Forum')
            ->setContent('Content')
            ->setAuthor($user);

        $this->em->persist($forumPost);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertNotNull($this->userRepository->find($user->getId()));
    }

    public function testCommandFailsWithForumResponseRelation(): void
    {
        $user = $this->createAnonymousUser();

        // Création d'un Forum valide
        $forum = new Forum();
        $forum->setTitle('Test Forum')
            ->setContent('Contenu du forum')
            ->setAuthor($user);
        $this->em->persist($forum);

        $response = new ForumResponse();
        $response->setContent('Response')
            ->setAuthor($user)
            ->setForum($forum); // Correction du nom de la méthode

        $this->em->persist($response);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testCommandFailsWithEventOrganizerRelation(): void
    {
        $user = $this->createAnonymousUser();

        $event = new Event();
        $event->setTitle('Test Event')
            ->setDescription('Description de test')
            ->setLocation('Paris')
            ->setStartDate(new \DateTime())
            ->setEndDate(new \DateTime('+1 hour'))
            ->setMaxAttendees(10)
            ->setOrganizer($user);

        $this->em->persist($event);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testCommandFailsWithEventAttendance(): void
    {
        $user = $this->createAnonymousUser();

        // Création d'un organisateur valide
        $organizer = new User();
        $organizer->setPseudo('Organisateur')
            ->setEmail('orga@example.com')
            ->setPassword('password');
        $this->em->persist($organizer);

        $event = new Event();
        $event->setTitle('Event')
            ->setDescription('Description')
            ->setLocation('Lyon')
            ->setStartDate(new \DateTime())
            ->setEndDate(new \DateTime('+1 hour'))
            ->setMaxAttendees(20)
            ->setOrganizer($organizer)
            ->addAttendee($user);

        $this->em->persist($event);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testCommandFailsWithRatingRelations(): void
    {
        $user = $this->createAnonymousUser();
        $otherUser = $this->userRepository->findOneBy(['email' => 'user@example.com']) ?? new User();

        // Test en tant que rater
        $rating1 = new Rating();
        $rating1->setRater($user)
            ->setRatedUser($otherUser)
            ->setScore(5);

        // Test en tant que rated
        $rating2 = new Rating();
        $rating2->setRater($otherUser)
            ->setRatedUser($user)
            ->setScore(3);

        $this->em->persist($rating1);
        $this->em->persist($rating2);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testCommandFailsWithOutgoingMessage(): void
    {
        $user = $this->createAnonymousUser();
        $otherUser = $this->userRepository->findOneBy(['email' => 'user@example.com']) ?? new User();

        $message = new Message();
        $message->setTitle('Test')
            ->setContent('Hello')
            ->setSender($user)
            ->setReceiver($otherUser);

        $this->em->persist($message);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testCommandFailsWithMultipleRelations(): void
    {
        $user = $this->createAnonymousUser();
        $otherUser = $this->userRepository->findOneBy([]) ?? new User();

        // Création d'un Forum valide
        $forum = new Forum();
        $forum->setTitle('Forum Test')
            ->setContent('Contenu')
            ->setAuthor($otherUser);
        $this->em->persist($forum);

        // Création des relations
        $blogPost = (new BlogPost())
            ->setTitle('Test')
            ->setContent('Content')
            ->setAuthor($user);

        $forumResponse = (new ForumResponse())
            ->setContent('Test')
            ->setAuthor($user)
            ->setForum($forum);

        $rating = (new Rating())
            ->setRater($user)
            ->setRatedUser($otherUser) // Ajout du champ requis
            ->setScore(5);

        $this->em->persist($blogPost);
        $this->em->persist($forumResponse);
        $this->em->persist($rating);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertNotNull($this->userRepository->find($user->getId()));
    }

    public function testCommandFailsWithIncomingMessage(): void
    {
        $user = $this->createAnonymousUser();
        $otherUser = new User();
        $otherUser->setPseudo('Other')->setEmail('other@example.com')->setPassword('pass');
        $this->em->persist($otherUser);

        $message = new Message();
        $message->setTitle('Test')
            ->setContent('Hi Anonymous')
            ->setSender($otherUser)
            ->setReceiver($user);
        $this->em->persist($message);
        $this->em->flush();

        $commandTester = new CommandTester($this->application->find('app:cleanup-anonymous-user'));
        $commandTester->execute([]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertNotNull($this->userRepository->find($user->getId()));
    }
}
