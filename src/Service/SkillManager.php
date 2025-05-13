<?php

namespace App\Service;

use App\Entity\{Skill, User};
use Doctrine\ORM\EntityManagerInterface;

class SkillManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function createSkill(Skill $skill): void
    {
        $this->em->persist($skill);
        $this->em->flush();
    }

    public function handleSkillSubmission(User $user, Skill $skill): void
    {
        $this->validateSkillNotOwned($user, $skill);

        $user->addSkill($skill);
        $this->em->flush();
    }

    public function removeUserSkill(User $user, Skill $skill): void
    {
        $this->validateSkillOwned($user, $skill);

        $user->removeSkill($skill);
        $this->em->flush();
    }

    private function validateSkillNotOwned(User $user, Skill $skill): void
    {
        if ($user->hasSkill($skill)) {
            throw new \RuntimeException('Cette compétence est déjà associée à l\'utilisateur');
        }
    }

    private function validateSkillOwned(User $user, Skill $skill): void
    {
        if (!$user->hasSkill($skill)) {
            throw new \RuntimeException('Cette compétence n\'est pas associée à l\'utilisateur');
        }
    }
}
