<?php

namespace App\Service;

use App\Entity\User;
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
        // 1. Suppression du contenu créé
        $this->deleteCreatedContent($user);

        // 2. Anonymisation des participations si elles existent
        if ($this->hasParticipations($user)) {
            $this->anonymizer->anonymizeParticipations($user);
        }

        // 3. Suppression définitive TOUJOURS effectuée
        $this->fullDeleteUser($user);

        $this->em->flush();
    }

    private function deleteCreatedContent(User $user): void
    {
        // Suppression des blogs
        foreach ($user->getBlogPosts() as $blogPost) {
            if ($blogPost->getImageName()) {
                $imagePath = $this->params->get('blog_images_directory') . '/' . $blogPost->getImageName();
                $this->filesystem->remove($imagePath);
            }
            $this->em->remove($blogPost);
        }

        // Suppression des forums
        foreach ($user->getForums() as $forum) {
            if ($forum->getImageName()) {
                $imagePath = $this->params->get('forum_images_directory') . '/' . $forum->getImageName();
                $this->filesystem->remove($imagePath);
            }
            $this->em->remove($forum);
        }

        // Suppression des événements organisés
        foreach ($user->getOrganizedEvents() as $event) {
            if ($event->getImageName()) {
                $imagePath = $this->params->get('event_images_directory') . '/' . $event->getImageName();
                $this->filesystem->remove($imagePath);
            }
            $this->em->remove($event);
        }

        $this->em->flush();
    }

    private function hasParticipations(User $user): bool
    {
        return $user->getSentMessages()->count() > 0 ||
            $user->getReceivedMessages()->count() > 0 ||
            $user->getForumResponses()->count() > 0 ||
            $user->getAttendedEvents()->count() > 0 ||
            $user->getRatingsReceived()->count() > 0 ||
            $user->getRatingsGiven()->count() > 0 ||
            $user->getConversationDeletions()->count() > 0;
    }

    private function fullDeleteUser(User $user): void
    {
        // Suppression de l'image de profil
        if ($user->getProfileImage()) {
            $imagePath = $this->params->get('profile_images_directory') . '/' . $user->getProfileImage();
            $this->filesystem->remove($imagePath);
        }

        // Suppression définitive de l'utilisateur
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
