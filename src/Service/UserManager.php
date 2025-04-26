<?php

namespace App\Service;

use App\Entity\{User, ConversationDeletion};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserAnonymizer $anonymizer,
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $params,
        private readonly Filesystem $filesystem
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
        if ($this->shouldBeFullyDeleted($user)) {
            $this->fullDelete($user); // Suppression physique
        } else {
            $this->anonymizer->anonymize($user); // Anonymisation
            $user->setDeletedAt(new \DateTimeImmutable()); // Soft delete
        }

        $this->em->flush();
    }

    public function shouldBeFullyDeleted(User $user): bool
    {
        return $this->countUserRelationships($user) === 0;
    }

    private function countUserRelationships(User $user): int
    {
        return
            $user->getSentMessages()->count() +
            $user->getReceivedMessages()->count() +
            $user->getBlogPosts()->count() +
            $user->getForums()->count() +
            $user->getForumResponses()->count() +
            $user->getOrganizedEvents()->count() +
            $user->getAttendedEvents()->count() +
            $user->getRatingsReceived()->count();
    }

    private function fullDelete(User $user): void
    {
        if ($user->getProfileImage()) {
            $directory = $this->params->get('profile_images_directory');
            $imagePath = $directory . '/' . $user->getProfileImage();
            if ($this->filesystem->exists($imagePath)) {
                $this->filesystem->remove($imagePath);
            }
        }

        $this->em->remove($user);
    }

    private function cleanUserData(User $user): void
    {
        $this->cleanConversationDeletions($user);
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

    public function saveUser(User $user, bool $flush = true): void
    {
        $this->em->persist($user);

        if ($flush) {
            $this->em->flush();
        }
    }
}
