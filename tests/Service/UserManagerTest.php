<?php

namespace App\Tests\Service;

use App\Entity\{BlogPost, Event, Forum, Message, User};
use App\Repository\UserRepository;
use App\Service\UserAnonymizer;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use ReflectionMethod;

class UserManagerTest extends TestCase
{
    private $em;
    private $anonymizer;
    private $userRepository;
    private $params;
    private $filesystem;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->anonymizer = $this->createMock(UserAnonymizer::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
    }

    private function createManager(): UserManager
    {
        return new UserManager(
            $this->em,
            $this->anonymizer,
            $this->userRepository,
            $this->params,
            $this->filesystem
        );
    }

    public function testPromoteToAdminAddsRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);

        $this->em->expects($this->once())->method('flush');

        $manager = $this->createManager();
        $manager->promoteToAdmin($user);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testPromoteToAdminWhenAlreadyAdmin(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->em->expects($this->never())->method('flush');

        $manager = $this->createManager();
        $manager->promoteToAdmin($user);

        $this->assertCount(1, array_filter($user->getRoles(), fn($role) => $role === 'ROLE_ADMIN'));
    }

    public function testDemoteFromAdminRemovesRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        // Définir l'ID avec réflexion
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($user, 1);

        $currentUser = new User();
        $property->setValue($currentUser, 2); // ID différent

        $this->em->expects($this->once())->method('flush');

        $manager = $this->createManager();
        $manager->demoteFromAdmin($user, $currentUser);

        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testDemoteFromAdminThrowsWhenSelfDemotion(): void
    {
        $user = new User();

        $this->expectException(AccessDeniedException::class);

        $manager = $this->createManager();
        $manager->demoteFromAdmin($user, $user);
    }

    public function testDeleteUserHandlesAllAssets(): void
    {
        $user = new User();
        $user->setProfileImage('photo.jpg');

        // Configurer les entités associées
        $user->addSentMessage(new Message());

        $blogPost = new BlogPost();
        $blogPost->setImageName('blog.jpg');
        $user->addBlogPost($blogPost);

        $forum = new Forum();
        $forum->setImageName('forum.jpg');
        $user->addForum($forum);

        $event = new Event();
        $event->setImageName('event.jpg');
        $user->addOrganizedEvent($event);

        // Configurer les paramètres
        $this->params->method('get')
            ->willReturnMap([
                ['blog_images_directory', '/dummy/blog'],
                ['forum_images_directory', '/dummy/forum'],
                ['event_images_directory', '/dummy/event'],
                ['profile_images_directory', '/dummy/profile']
            ]);

        // Vérifications Filesystem
        $this->filesystem->expects($this->exactly(4))->method('remove')
            ->withConsecutive(
                ['/dummy/blog/blog.jpg'],
                ['/dummy/forum/forum.jpg'],
                ['/dummy/event/event.jpg'],
                ['/dummy/profile/photo.jpg']
            );

        // Vérifications EntityManager
        $this->em->expects($this->exactly(4))->method('remove')
            ->withConsecutive(
                [$blogPost],
                [$forum],
                [$event],
                [$user]
            );

        $this->anonymizer->expects($this->once())->method('anonymizeParticipations');

        $manager = $this->createManager();
        $manager->deleteUser($user);
    }

    public function testSaveUserPersistsAndFlushes(): void
    {
        $user = new User();

        $this->em->expects($this->once())->method('persist')->with($user);
        $this->em->expects($this->once())->method('flush');

        $manager = $this->createManager();
        $manager->saveUser($user);
    }

    public function testSaveUserWithoutFlush(): void
    {
        $user = new User();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $manager = $this->createManager();
        $manager->saveUser($user, false);
    }

    public function testDeleteUserWithoutParticipationsSkipsAnonymization(): void
    {
        $this->anonymizer->expects($this->never())->method('anonymizeParticipations');

        $manager = $this->createManager();
        $manager->deleteUser(new User());
    }

    public function testHasParticipationsDetection(): void
    {
        $user = new User();
        $user->addSentMessage(new Message());

        $method = new ReflectionMethod(UserManager::class, 'hasParticipations');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->createManager(), $user));
    }

    public function testProfileImageDeletion(): void
    {
        $user = new User();
        $user->setProfileImage('profil.jpg');

        $this->params->method('get')
            ->with('profile_images_directory')
            ->willReturn('/dummy');

        $this->filesystem->expects($this->once())
            ->method('remove')
            ->with('/dummy/profil.jpg');

        $method = new ReflectionMethod(UserManager::class, 'fullDeleteUser');
        $method->setAccessible(true);

        $method->invoke($this->createManager(), $user);
    }

    public function testFullDeleteProcessFlow(): void
    {
        $user = new User();

        $this->em->expects($this->once())->method('remove')->with($user);
        $this->em->expects($this->exactly(2))->method('flush');

        $manager = $this->createManager();
        $manager->deleteUser($user);
    }
}
