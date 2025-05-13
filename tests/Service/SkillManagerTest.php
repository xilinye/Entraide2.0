<?php

namespace App\Tests\Service;

use App\Entity\{Skill, User};
use App\Service\SkillManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

class SkillManagerTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    private SkillManager $skillManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->skillManager = new SkillManager($this->entityManager);
    }

    public function testCreateSkill(): void
    {
        $skill = new Skill();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($skill));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->createSkill($skill);
    }

    /**
     * @return User&MockObject
     */
    private function createUserMock(bool $hasSkill)
    {
        $user = $this->createMock(User::class);
        $user->method('hasSkill')->willReturn($hasSkill);
        return $user;
    }

    public function testSuccessfullyAddSkillToUser(): void
    {
        $skill = new Skill();
        /** @var User&MockObject $user */
        $user = $this->createUserMock(false);

        $user->expects($this->once())
            ->method('addSkill')
            ->with($this->identicalTo($skill));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->handleSkillSubmission($user, $skill);
    }

    public function testThrowWhenAddingExistingSkill(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createUserMock(true);
        $skill = new Skill();

        $user->expects($this->never())
            ->method('addSkill');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cette compétence est déjà associée à l\'utilisateur');

        $this->skillManager->handleSkillSubmission($user, $skill);
    }

    public function testSuccessfullyRemoveSkillFromUser(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createUserMock(true);
        $skill = new Skill();

        $user->expects($this->once())
            ->method('removeSkill')
            ->with($this->identicalTo($skill));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->skillManager->removeUserSkill($user, $skill);
    }

    public function testThrowWhenRemovingUnownedSkill(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createUserMock(false);
        $skill = new Skill();

        $user->expects($this->never())
            ->method('removeSkill');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cette compétence n\'est pas associée à l\'utilisateur');

        $this->skillManager->removeUserSkill($user, $skill);
    }
}
