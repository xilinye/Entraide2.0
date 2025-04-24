<?php

namespace App\Service;

use App\Entity\{User, ConversationDeletion, BlogPost, Forum, ForumResponse, Rating, Event};
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
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        // Transfert des réponses de forum
        $this->em->createQueryBuilder()
            ->update(ForumResponse::class, 'fr')
            ->set('fr.author', ':anonymous')
            ->where('fr.author = :user')
            ->setParameter('anonymous', $anonymousUser)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Transfert des événements organisés
        $this->em->createQueryBuilder()
            ->update(Event::class, 'e')
            ->set('e.organizer', ':anonymous')
            ->where('e.organizer = :user')
            ->setParameter('anonymous', $anonymousUser)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Gestion des évaluations
        // Supprime les évaluations où l'utilisateur est l'auteur (rater)
        $this->em->createQueryBuilder()
            ->delete(Rating::class, 'r')
            ->where('r.rater = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Transfère les évaluations où l'utilisateur est évalué (ratedUser) à l'utilisateur anonyme
        $this->em->createQueryBuilder()
            ->update(Rating::class, 'r')
            ->set('r.ratedUser', ':anonymousUser')
            ->where('r.ratedUser = :user')
            ->setParameter('anonymousUser', $anonymousUser)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        $this->transferConversationDeletions($user);
        $this->cleanOtherUserDeletions($user);
        $this->anonymizer->anonymize($user);
        // Ne PAS appeler remove($user) ici
        $this->em->flush(); // S'assurer que les changements sont sauvegardés
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
        // Suppression des réponses de forum
        $this->em->createQueryBuilder()
            ->delete(ForumResponse::class, 'fr')
            ->where('fr.author = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Suppression des événements organisés
        $this->em->createQueryBuilder()
            ->delete(Event::class, 'e')
            ->where('e.organizer = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Suppression des notes données/reçues
        $this->em->createQueryBuilder()
            ->delete(Rating::class, 'r')
            ->where('r.rater = :user OR r.ratedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

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
