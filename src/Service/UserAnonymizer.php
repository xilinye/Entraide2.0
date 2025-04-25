<?php

namespace App\Service;

use App\Entity\{User, Message, Rating};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class UserAnonymizer
{
    private function getParameter(string $name): string
    {
        return $this->params->get($name);
    }

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
        private readonly Filesystem $filesystem
    ) {}

    public function anonymize(User $user): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        $this->processMessages($user, $anonymousUser);
        $this->processBlogPosts($user, $anonymousUser);
        $this->processEvents($user, $anonymousUser);
        $this->processForums($user, $anonymousUser);
        $this->processForumResponses($user, $anonymousUser);
        $this->processRatings($user, $anonymousUser);
    }

    private function processMessages(User $user, User $anonymousUser): void
    {
        foreach ($user->getSentMessages() as $message) {
            $receiver = $message->getReceiver();
            if ($receiver->isAnonymous()) {
                $this->em->remove($message);
            } else {
                $message->setSender($anonymousUser);
            }
        }

        foreach ($user->getReceivedMessages() as $message) {
            $sender = $message->getSender();
            if ($sender->isAnonymous()) {
                $this->em->remove($message);
            } else {
                $message->setReceiver($anonymousUser);
            }
        }
    }

    private function processBlogPosts(User $user, User $anonymousUser): void
    {
        foreach ($user->getBlogPosts() as $post) {
            if ($post->getImageName()) {
                $imagePath = $this->getParameter('blog_images_directory') . '/' . $post->getImageName();
                if ($this->filesystem->exists($imagePath)) {
                    $this->filesystem->remove($imagePath);
                }
            }
            $this->em->remove($post);
        }
    }

    private function processEvents(User $user, User $anonymousUser): void
    {
        foreach ($user->getOrganizedEvents() as $event) {
            if ($event->getImageName()) {
                $imagePath = $this->getParameter('event_images_directory') . '/' . $event->getImageName();
                if ($this->filesystem->exists($imagePath)) {
                    $this->filesystem->remove($imagePath);
                }
            }
            $this->em->remove($event);
        }

        foreach ($user->getAttendedEvents() as $event) {
            $event->removeAttendee($user);
            $event->addAttendee($anonymousUser);
        }
    }

    private function processForums(User $user, User $anonymousUser): void
    {
        foreach ($user->getForums() as $forum) {
            if ($forum->getImageName()) {
                $imagePath = $this->getParameter('forum_images_directory') . '/' . $forum->getImageName();
                if ($this->filesystem->exists($imagePath)) {
                    $this->filesystem->remove($imagePath);
                }
            }
            $this->em->remove($forum);
        }
    }

    private function processForumResponses(User $user, User $anonymousUser): void
    {
        foreach ($user->getForumResponses() as $response) {
            $response->setAuthor($anonymousUser);
            if ($response->getImageName()) {
                $imagePath = $this->getParameter('forumResponse_images_directory') . '/' . $response->getImageName();
                if ($this->filesystem->exists($imagePath)) {
                    $this->filesystem->remove($imagePath);
                }
            }
        }
    }

    private function processRatings(User $user, User $anonymousUser): void
    {
        $ratingsGiven = $this->em->getRepository(Rating::class)->findBy(['rater' => $user]);
        foreach ($ratingsGiven as $rating) {
            $rating->setRater($anonymousUser);
        }

        $ratingsReceived = $this->em->getRepository(Rating::class)->findBy(['ratedUser' => $user]);
        foreach ($ratingsReceived as $rating) {
            $rating->setRatedUser($anonymousUser);
        }
    }
}
