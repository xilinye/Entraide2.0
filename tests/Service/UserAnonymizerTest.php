<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Event;
use App\Repository\UserRepository;
use App\Service\UserAnonymizer;
use Doctrine\Common\Collections\ArrayCollection;
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

        // Configuration du mock de requête avec gestion de setParameters()
        $queryMock = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $queryMock->method('setParameters')->willReturnSelf();
        $queryMock->method('execute')->willReturn(0);
        $this->em->method('createQuery')->willReturn($queryMock);
    }

    public function testAnonymizeParticipationsCallsFindOrCreateAnonymousUser(): void
    {
        $user = new User();
        $this->userRepository->expects($this->once())
            ->method('findOrCreateAnonymousUser')
            ->willReturn($this->anonymousUser);

        $anonymizer = new UserAnonymizer($this->userRepository, $this->em, $this->parameterBag, $this->filesystem);
        $anonymizer->anonymizeParticipations($user);

        // Si aucune exception n'est levée, le test passe
        $this->assertTrue(true);
    }

    public function testTransferParticipationsUpdatesEventAttendees(): void
    {
        $user = $this->createPartialMock(User::class, ['getAttendedEvents']);
        $event1 = $this->createMock(Event::class);
        $event1->expects($this->once())->method('removeAttendee')->with($user);
        $event1->expects($this->once())->method('addAttendee')->with($this->anonymousUser);

        $event2 = $this->createMock(Event::class);
        $event2->expects($this->once())->method('removeAttendee')->with($user);
        $event2->expects($this->once())->method('addAttendee')->with($this->anonymousUser);

        $user->method('getAttendedEvents')->willReturn(new ArrayCollection([$event1, $event2]));
        $this->userRepository->method('findOrCreateAnonymousUser')->willReturn($this->anonymousUser);

        $anonymizer = new UserAnonymizer($this->userRepository, $this->em, $this->parameterBag, $this->filesystem);
        $anonymizer->anonymizeParticipations($user);

        // Vérification implicite via les expects sur les événements
        $this->assertTrue(true);
    }

    public function testAnonymizeParticipationsFlushesAfterTransfer(): void
    {
        $user = new User();
        $this->userRepository->method('findOrCreateAnonymousUser')->willReturn($this->anonymousUser);

        $this->em->expects($this->once())->method('flush');

        $anonymizer = new UserAnonymizer($this->userRepository, $this->em, $this->parameterBag, $this->filesystem);
        $anonymizer->anonymizeParticipations($user);
    }
}
