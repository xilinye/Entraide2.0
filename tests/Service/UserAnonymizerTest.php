<?php

namespace App\Tests\Service;

use App\Entity\{Event, User};
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
    private $queryMock;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->anonymousUser = new User();
        $this->queryMock = $this->createMock(AbstractQuery::class);
        $this->queryMock->method('setParameters')->willReturnSelf();
        $this->queryMock->method('execute')->willReturn(0);

        $this->em->method('createQuery')->willReturn($this->queryMock);
    }

    public function testAnonymizeParticipationsFullCoverage()
    {
        $expectedDqls = [
            'UPDATE App\Entity\Message m SET m.sender = :anon WHERE m.sender = :user',
            'UPDATE App\Entity\Message m SET m.receiver = :anon WHERE m.receiver = :user',
            'UPDATE App\Entity\ForumResponse fr SET fr.author = :anon WHERE fr.author = :user',
            'UPDATE App\Entity\Rating r SET r.rater = :anon WHERE r.rater = :user',
            'UPDATE App\Entity\Rating r SET r.ratedUser = :anon WHERE r.ratedUser = :user',
            'UPDATE App\Entity\ConversationDeletion cd SET cd.user = :anon WHERE cd.user = :user'
        ];

        $actualDqls = [];
        $actualParameters = [];

        // Configuration du mock pour capturer les requêtes DQL
        $this->em->method('createQuery')->willReturnCallback(
            function ($dql) use (&$actualDqls) {
                $actualDqls[] = $dql;
                return $this->queryMock;
            }
        );

        $this->queryMock->method('setParameters')->willReturnCallback(
            function ($parameters) use (&$actualParameters) {
                $actualParameters[] = $parameters;
                return $this->queryMock;
            }
        );

        // Création d'un vrai utilisateur
        $user = new User();
        $user->setPseudo('real_user');
        $user->setEmail('user@example.com');
        $user->setPassword('password');

        // Définition de l'ID via réflexion
        $reflection = new \ReflectionClass(User::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, 1);

        // Configuration des événements
        $event1 = $this->createMock(Event::class);
        $event1->expects($this->once())
            ->method('removeAttendee')
            ->with($user);
        $event1->expects($this->once())
            ->method('addAttendee')
            ->with($this->anonymousUser);

        $event2 = $this->createMock(Event::class);
        $event2->expects($this->once())
            ->method('removeAttendee')
            ->with($user);
        $event2->expects($this->once())
            ->method('addAttendee')
            ->with($this->anonymousUser);

        // Injection des événements dans la propriété privée
        $attendedEventsProperty = $reflection->getProperty('attendedEvents');
        $attendedEventsProperty->setAccessible(true);
        $attendedEventsProperty->setValue($user, new ArrayCollection([$event1, $event2]));

        // Configuration de l'utilisateur anonyme
        $idProperty->setValue($this->anonymousUser, 999);
        $this->userRepository->method('findOrCreateAnonymousUser')
            ->willReturn($this->anonymousUser);

        $this->em->expects($this->once())->method('flush');

        // Exécution
        $anonymizer = new UserAnonymizer(
            $this->userRepository,
            $this->em,
            $this->parameterBag,
            $this->filesystem
        );
        $anonymizer->anonymizeParticipations($user);

        // Vérifications
        $this->assertCount(6, $actualDqls, '6 requêtes DQL doivent être exécutées');
        foreach ($expectedDqls as $expectedDql) {
            $this->assertContains($expectedDql, $actualDqls, "La requête '$expectedDql' doit être présente");
        }

        foreach ($actualParameters as $params) {
            $this->assertSame($this->anonymousUser, $params['anon'], "Le paramètre 'anon' doit être l'utilisateur anonyme");
            $this->assertSame($user, $params['user'], "Le paramètre 'user' doit être l'utilisateur original");
        }
    }

    public function testAnonymizeUserWithoutParticipations()
    {
        $user = new User();
        $this->userRepository->method('findOrCreateAnonymousUser')
            ->willReturn($this->anonymousUser);
        $this->em->expects($this->once())
            ->method('flush');

        $anonymizer = new UserAnonymizer(
            $this->userRepository,
            $this->em,
            $this->parameterBag,
            $this->filesystem
        );
        $anonymizer->anonymizeParticipations($user);

        // Vérifie qu'aucune exception n'est levée et que flush est appelé
        $this->assertTrue(true);
    }
}
