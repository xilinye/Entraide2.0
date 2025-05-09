<?php

namespace App\Tests\Service;

use App\Entity\{Skill, User};
use App\Service\SkillManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use RuntimeException;

class SkillManagerTest extends TestCase
{
    private $entityManager;
    private $urlGenerator;
    private $requestStack;
    private $skillManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->skillManager = new SkillManager(
            $this->entityManager,
            $this->urlGenerator,
            $this->requestStack
        );
    }

    // Teste la création d'une compétence
    public function testCreateSkillCallsPersistAndFlush(): void
    {
        $skill = new Skill();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($skill);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->createSkill($skill);
    }

    // Teste l'ajout d'une compétence à un utilisateur
    public function testHandleSkillSubmissionAddsSkillAndFlushes(): void
    {
        $user = $this->createMock(User::class);
        $skill = new Skill();

        $user->expects($this->once())
            ->method('hasSkill')
            ->with($skill)
            ->willReturn(false);

        $user->expects($this->once())
            ->method('addSkill')
            ->with($skill);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->handleSkillSubmission($user, $skill);
    }

    // Teste l'exception lors de l'ajout d'une compétence existante
    public function testHandleSkillSubmissionWhenSkillExistsThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $skill = new Skill();

        $user->expects($this->once())
            ->method('hasSkill')
            ->with($skill)
            ->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cette compétence est déjà associée à l\'utilisateur');

        $this->skillManager->handleSkillSubmission($user, $skill);
    }

    // Teste la suppression d'une compétence d'un utilisateur
    public function testRemoveUserSkillRemovesSkillAndFlushes(): void
    {
        $user = $this->createMock(User::class);
        $skill = new Skill();

        $user->expects($this->once())
            ->method('hasSkill')
            ->with($skill)
            ->willReturn(true);

        $user->expects($this->once())
            ->method('removeSkill')
            ->with($skill);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->removeUserSkill($user, $skill);
    }

    // Teste l'exception lors de la suppression d'une compétence non associée
    public function testRemoveUserSkillWhenSkillNotPresentThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $skill = new Skill();

        $user->expects($this->once())
            ->method('hasSkill')
            ->with($skill)
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cette compétence n\'est pas associée à l\'utilisateur');

        $this->skillManager->removeUserSkill($user, $skill);
    }
}
