<?php

namespace App\Tests\Command;

use App\Entity\{User, Message, BlogPost, Forum, ForumResponse, Event, Rating};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Ramsey\Uuid\Uuid;

class CleanupAnonymousUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::$kernel->getContainer()->get('doctrine')->getManager();

        $application = new Application(self::$kernel);
        $command = $application->find('app:cleanup-anonymous-user');
        $this->commandTester = new CommandTester($command);

        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        $this->em->getConnection()->executeStatement('DELETE FROM user');
        $this->em->clear();

        parent::tearDown();
    }

    private function createAnonymousUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('anonymous_') . '@example.com')
            ->setRoles(['ROLE_ANONYMOUS'])
            ->setPassword('password')
            ->setPseudo(Uuid::uuid4()->toString());

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testNoAnonymousUser(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Aucun utilisateur anonyme trouvé', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testSuccessfulCleanup(): void
    {
        $user = $this->createAnonymousUser();

        // Créer des messages orphelins
        $this->createMessages($user, 5);

        $this->commandTester->execute([]);

        $this->assertUserDeleted($user);
        $this->assertStringContainsString('Messages traités : 5', $this->commandTester->getDisplay());
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    private function createBlogPost(User $user): void
    {
        $blogPost = new BlogPost();
        $blogPost->setTitle('Test Blog')
            ->setContent('Contenu test')
            ->setAuthor($user);

        $this->em->persist($blogPost);
        $this->em->flush();
    }

    public function testBlockedByActiveRelations(): void
    {
        $user = $this->createAnonymousUser();
        $this->createBlogPost($user);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('relations actives détectées', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testDryRun(): void
    {
        $user = $this->createAnonymousUser();
        $this->createMessages($user, 3);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[DRY RUN]', $output);
        $this->assertStringContainsString('Messages traités : 3', $output);
        $this->assertUserExists($user);
    }

    public function testLargeDatasetPerformance(): void
    {
        $user = $this->createAnonymousUser();
        $this->createMessages($user, 1000);

        $start = microtime(true);
        $this->commandTester->execute([]);
        $duration = microtime(true) - $start;

        $this->assertLessThan(10, $duration, 'Le traitement doit être rapide');
        $this->assertUserDeleted($user);
    }

    public function testTransactionRollback(): void
    {
        $user = $this->createAnonymousUser();

        // Simuler une erreur après suppression partielle
        $this->em->getEventManager()->addEventListener('onFlush', function () {
            throw new \RuntimeException('Simulated error');
        });

        $this->commandTester->execute([]);

        $this->assertUserExists($user);
        $this->assertStringContainsString('Erreur lors du nettoyage', $this->commandTester->getDisplay());
    }

    private function createMessages(User $user, int $count): void
    {
        $batchSize = 100;
        $userId = $user->getId();

        for ($i = 0; $i < $count; $i++) {
            // Recharger l'utilisateur à chaque batch
            $managedUser = $this->em->find(User::class, $userId);

            $message = new Message();
            $message->setSender($managedUser)
                ->setReceiver($managedUser)
                ->setContent("Message $i")
                ->setTitle("Test $i");

            $this->em->persist($message);

            if (0 === ($i % $batchSize)) {
                $this->em->flush();
                $this->em->clear(Message::class);
            }
        }

        $this->em->flush();
        $this->em->clear();
    }

    private function assertUserDeleted(User $user): void
    {
        $exists = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertNull($exists, 'L\'utilisateur doit être supprimé');
    }

    private function assertUserExists(User $user): void
    {
        $exists = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($exists, 'L\'utilisateur doit toujours exister');
    }
    private function createForumPost(User $user): void
    {
        $forum = new Forum();
        $forum->setTitle('Test Forum')
            ->setContent('Contenu forum')
            ->setAuthor($user);

        $this->em->persist($forum);
        $this->em->flush();
    }

    private function createForumResponse(User $user): void
    {
        $forum = $this->createForumPost($user); // Nécessite un forum existant

        $response = new ForumResponse();
        $response->setContent('Réponse test')
            ->setAuthor($user)
            ->setForum($forum);

        $this->em->persist($response);
        $this->em->flush();
    }

    private function createEvent(User $user): Event
    {
        $event = new Event();
        $event->setTitle('Événement test')
            ->setDescription('Description')
            ->setLocation('Lieu test')
            ->setMaxAttendees(10)
            ->setStartDate(new \DateTimeImmutable())
            ->setEndDate(new \DateTimeImmutable('+1 hour'))
            ->setOrganizer($user);

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    private function createRating(User $rater, User $rated): void
    {
        $rating = new Rating();
        $rating->setScore(5)
            ->setComment('Test')
            ->setRater($rater)
            ->setRatedUser($rated);

        $this->em->persist($rating);
        $this->em->flush();
    }

    public function testBlockedByForumPost(): void
    {
        $user = $this->createAnonymousUser();
        $this->createForumPost($user);

        $this->commandTester->execute([]);

        $this->assertUserExists($user);
        $this->assertStringContainsString('1 relations actives détectées', $this->commandTester->getDisplay());
    }

    public function testBlockedByEventOrganization(): void
    {
        $user = $this->createAnonymousUser();
        $this->createEvent($user);

        $this->commandTester->execute([]);

        $this->assertUserExists($user);
        $this->assertStringContainsString(
            'relations actives détectées',
            $this->commandTester->getDisplay()
        );
    }

    private function checkRelationsCount(User $user): int
    {
        $count = 0;
        $count += $this->em->getRepository(BlogPost::class)->count(['author' => $user]);
        $count += $this->em->getRepository(Forum::class)->count(['author' => $user]);
        $count += $this->em->getRepository(Event::class)->count(['organizer' => $user]);

        // Ajoutez les participants aux événements
        $count += $this->em->createQueryBuilder()
            ->select('COUNT(1)')
            ->from(Event::class, 'e')
            ->join('e.attendees', 'a')
            ->where('a = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function testMultipleRelationsCount(): void
    {
        $user = $this->createAnonymousUser();
        $this->createBlogPost($user);
        $this->createForumPost($user);
        $event = $this->createEvent($user);
        $event->addAttendee($user);
        $this->em->flush();

        $this->assertSame(4, $this->checkRelationsCount($user), 'Pré-condition non satisfaite');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('4 relations actives détectées', $output); // Mise à jour de 3 à 4
    }

    public function testPartialDeletionWithActiveRelations(): void
    {
        $user = $this->createAnonymousUser();
        $this->createMessages($user, 5);
        $this->createBlogPost($user); // Bloque la suppression

        $this->commandTester->execute([]);

        // Vérifie que les messages sont supprimés mais pas l'utilisateur
        $this->assertUserExists($user);
        $this->assertCount(0, $this->em->getRepository(Message::class)->findBy(['sender' => $user]));
    }

    public function testComplexEventRelations(): void
    {
        $user = $this->createAnonymousUser();
        $event = $this->createEvent($user);
        $event->addAttendee($user);

        $this->commandTester->execute([]);

        // Doit compter 2 relations (organisateur + participant)
        $this->assertStringContainsString('2 relations actives détectées', $this->commandTester->getDisplay());
    }

    public function testExactErrorMessageFormat(): void
    {
        $user = $this->createAnonymousUser();
        $this->createBlogPost($user);
        $this->createForumPost($user);

        $this->commandTester->execute([]);

        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[ERROR] Impossible de supprimer l\'utilisateur anonyme', $display);
        $this->assertMatchesRegularExpression('/\d+ relations actives détectées/', $display);
    }
}
