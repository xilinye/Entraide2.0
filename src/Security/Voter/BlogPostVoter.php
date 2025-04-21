<?php

namespace App\Security\Voter;

use App\Entity\BlogPost;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BlogPostVoter extends Voter
{
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof BlogPost;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $post = $subject;

        return $user === $post->getAuthor() || $user->hasRole('ROLE_ADMIN');
    }
}
