<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new CustomUserMessageAuthenticationException('Type d\'utilisateur non supporté.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('UNVERIFIED_ACCOUNT');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Cette méthode est requise par l'interface mais peut rester vide
    }
}
