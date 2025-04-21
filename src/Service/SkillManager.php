<?php

namespace App\Service;

use App\Entity\{Skill, User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SkillManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack
    ) {}

    public function createSkill(Skill $skill): void
    {
        $this->em->persist($skill);
        $this->em->flush();
    }

    public function handleSkillSubmission(User $user, Skill $skill): void
    {
        if ($user->hasSkill($skill)) {
            throw new \RuntimeException('Cette compétence est déjà associée à l\'utilisateur');
        }

        $user->addSkill($skill);
        $this->em->flush();
    }

    public function removeUserSkill(User $user, Skill $skill): void
    {
        if (!$user->hasSkill($skill)) {
            throw new \RuntimeException('Cette compétence n\'est pas associée à l\'utilisateur');
        }

        $user->removeSkill($skill);
        $this->em->flush();
    }
}
