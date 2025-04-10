<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack
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

    public function deleteUserAccount(User $user): void
    {
        $this->anonymizeUser($user);
        $this->em->remove($user);
        $this->em->flush();
        $this->invalidateSession();
    }

    private function anonymizeUser(User $user): void
    {
        $user->setEmail('deleted_' . $user->getId() . '@example.com');
        $user->setPassword('deleted');
    }

    private function invalidateSession(): void
    {
        $this->tokenStorage->setToken(null);
        if ($request = $this->requestStack->getCurrentRequest()) {
            $request->getSession()?->invalidate();
        }
    }
}
