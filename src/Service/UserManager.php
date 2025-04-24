<?php

namespace App\Service;

use App\Entity\{User, ConversationDeletion, BlogPost};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserAnonymizer $anonymizer,
        private readonly UserRepository $userRepository
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
        // Nettoyage initial des dépendances
        $this->cleanConversationDeletions($user);

        if ($this->shouldDeletePermanently($user)) {
            $this->hardDeleteUser($user);
        } else {
            $this->handleSoftDelete($user); // Utilisation de la méthode dédiée
        }

        $this->em->flush();
    }

    private function cleanConversationDeletions(User $user): void
    {
        $this->em->createQueryBuilder()
            ->delete(ConversationDeletion::class, 'cd')
            ->where('cd.user = :user OR cd.otherUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    private function shouldDeletePermanently(User $user): bool
    {
        return $user->getSentMessages()->isEmpty()
            && $user->getReceivedMessages()->isEmpty();
    }

    private function handleSoftDelete(User $user): void
    {
        $this->transferConversationDeletions($user);
        $this->cleanOtherUserDeletions($user);
        $this->anonymizer->anonymize($user);
        $this->em->remove($user);
    }

    private function transferConversationDeletions(User $user): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        $this->em->createQueryBuilder()
            ->update(ConversationDeletion::class, 'cd')
            ->set('cd.user', ':anonymousUser')
            ->where('cd.user = :user')
            ->setParameter('user', $user)
            ->setParameter('anonymousUser', $anonymousUser)
            ->getQuery()
            ->execute();
    }

    private function cleanOtherUserDeletions(User $user): void
    {
        $this->em->createQueryBuilder()
            ->delete(ConversationDeletion::class, 'cd')
            ->where('cd.otherUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    private function hardDeleteUser(User $user): void
    {
        // Suppression massive des dépendances
        $this->em->createQueryBuilder()
            ->delete(ConversationDeletion::class, 'cd')
            ->where('cd.user = :user OR cd.otherUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Suppression en cascade des entités liées
        $this->em->createQueryBuilder()
            ->delete(BlogPost::class, 'bp')
            ->where('bp.author = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Suppression finale
        $this->em->remove($user);
    }

    public function saveUser(User $user, bool $flush = true): void
    {
        $this->em->persist($user);

        if ($flush) {
            $this->em->flush();
        }
    }
}
