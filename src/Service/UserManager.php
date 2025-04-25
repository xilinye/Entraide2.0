<?php

namespace App\Service;

use App\Entity\{User, ConversationDeletion};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Filesystem\Filesystem;


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
        $this->anonymizer->anonymize($user);
        $this->cleanConversationDeletions($user);

        // Suppression de l'image de profil
        if ($user->getProfileImage()) {
            $fs = new Filesystem();
            $path = $this->getParameter('profile_images_directory') . '/' . $user->getProfileImage();
            if ($fs->exists($path)) {
                $fs->remove($path);
            }
        }

        $this->em->remove($user);
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

    public function saveUser(User $user, bool $flush = true): void
    {
        $this->em->persist($user);

        if ($flush) {
            $this->em->flush();
        }
    }
}
