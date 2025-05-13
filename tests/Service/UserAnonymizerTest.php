<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserAnonymizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class UserAnonymizerTest extends TestCase
{
    private $userRepository;
    private $em;
    private $parameterBag;
    private $filesystem;
    private $anonymousUser;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->anonymousUser = new User();
    }

    public function testAnonymizeParticipationsCallsFindOrCreateAnonymousUser(): void
    {
        // Setup
        $user = new User();
        $this->configureQueryMock();

        $this->userRepository->expects($this->once())
            ->method('findOrCreateAnonymousUser')
            ->willReturn($this->anonymousUser);

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    public function testTransferParticipationsUpdatesEventAttendees(): void
    {
        // Setup
        $user = $this->createUserWithEvents(2);
        $this->configureQueryMock();

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    public function testAnonymizeParticipationsFlushesAfterTransfer(): void
    {
        // Setup
        $user = new User();
        $this->configureQueryMock();

        // Assertion
        $this->em->expects($this->once())->method('flush');

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    public function testTransferParticipationsUpdatesAllQueries(): void
    {
        // Setup
        $user = new User();
        $this->configureQueryMock();

        // Liste complète des requêtes attendues
        $expectedQueries = [
            'UPDATE App\Entity\Message m SET m.sender = :anon',
            'UPDATE App\Entity\Message m SET m.receiver = :anon',
            'UPDATE App\Entity\ForumResponse fr SET fr.author = :anon',
            'UPDATE App\Entity\Rating r SET r.rater = :anon',
            'UPDATE App\Entity\Rating r SET r.ratedUser = :anon',
            'UPDATE App\Entity\ConversationDeletion cd SET cd.user = :anon'
        ];

        // Vérification de toutes les requêtes
        $this->em->expects($this->exactly(6))
            ->method('createQuery')
            ->withConsecutive(...array_map(fn($q) => [$this->stringContains($q)], $expectedQueries));

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    public function testQueryParametersAreCorrectlySet(): void
    {
        // Setup
        $user = new User();

        // Ne pas appeler configureQueryMock() ici
        $this->userRepository->method('findOrCreateAnonymousUser')->willReturn($this->anonymousUser);

        // Création d'un mock de requête dédié
        $queryMock = $this->createMock(AbstractQuery::class);
        $queryMock->method('execute')->willReturn(0);
        $queryMock->expects($this->exactly(6))
            ->method('setParameters')
            ->with($this->callback(function ($params) use ($user) {
                return $params['anon'] === $this->anonymousUser
                    && $params['user'] === $user;
            }))
            ->willReturnSelf();

        $this->em->method('createQuery')->willReturn($queryMock);

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    public function testAnonymizeUserWithNoParticipations(): void
    {
        // Setup
        $user = $this->createUserWithEvents(0);
        $this->configureQueryMock();

        // Assertion
        $this->em->expects($this->once())->method('flush');

        // Execution
        $this->getAnonymizer()->anonymizeParticipations($user);
    }

    private function getAnonymizer(): UserAnonymizer
    {
        return new UserAnonymizer(
            $this->userRepository,
            $this->em,
            $this->parameterBag,
            $this->filesystem
        );
    }

    private function configureQueryMock(): void
    {
        $queryMock = $this->createMock(AbstractQuery::class);
        $queryMock->method('setParameters')->willReturnSelf();
        $queryMock->method('execute')->willReturn(0);
        $this->em->method('createQuery')->willReturn($queryMock);
        $this->userRepository->method('findOrCreateAnonymousUser')->willReturn($this->anonymousUser);
    }

    private function createUserWithEvents(int $count): User
    {
        $user = new User();
        $events = new ArrayCollection();

        for ($i = 0; $i < $count; $i++) {
            $event = $this->createMock(Event::class);
            $event->expects($this->once())->method('removeAttendee')->with($user);
            $event->expects($this->once())->method('addAttendee')->with($this->anonymousUser);
            $events->add($event);
        }

        // Injection par réflexion de la collection d'événements
        $reflectionClass = new \ReflectionClass(User::class);
        $property = $reflectionClass->getProperty('attendedEvents');
        $property->setAccessible(true);
        $property->setValue($user, $events);

        return $user;
    }
}
