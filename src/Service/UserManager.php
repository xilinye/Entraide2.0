<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private UserAnonymizer $anonymizer,
        private UserRepository $userRepository
    ) {}

    public function promoteToAdmin(User $user): void
    {
        $roles = $user->getRoles();

        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $user->setRoles(array_merge($roles, ['ROLE_ADMIN']));
            $this->em->flush();
        }
    }

    public function demoteFromAdmin(User $user, ?User $currentUser = null): void
    {
        if ($currentUser && $user->getId() === $currentUser->getId()) {
            throw new AccessDeniedException('Auto-rétrogradation interdite');
        }

        $roles = $user->getRoles();
        if (($key = array_search('ROLE_ADMIN', $roles, true)) !== false) {
            unset($roles[$key]);
            $user->setRoles(array_values($roles));
            $this->em->flush();
        }
    }

    public function deleteUser(User $user): void
    {
        $this->anonymizer->anonymize($user);

        // Déconnexion si l'utilisateur est connecté
        if ($this->tokenStorage->getToken()?->getUser() === $user) {
            $this->tokenStorage->setToken(null);
            $this->requestStack->getSession()->invalidate();
        }

        $this->userRepository->remove($user, true);
    }
}
