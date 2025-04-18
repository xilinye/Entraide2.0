<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EventVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Event && in_array($attribute, ['edit', 'delete']);
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $event = $subject;

        return $event->getOrganizer() === $user;
    }
}
