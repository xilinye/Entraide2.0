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
            throw new CustomUserMessageAuthenticationException(
                'Type d\'utilisateur non supporté.'
            );
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException(
                'Veuillez vérifier votre adresse email avant de vous connecter.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Ajouter ici d'autres vérifications post-authentification si nécessaire
        // Exemple : vérification de l'état du compte après l'authentification
    }
}
