<?php

namespace App\Service;

use App\Entity\{User, Message};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserAnonymizer
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em
    ) {}

    public function anonymize(User $user): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        $this->processMessages($user, $anonymousUser);
        $this->processBlogPosts($user, $anonymousUser);
    }

    private function processMessages(User $user, User $anonymousUser): void
    {
        // Pour les messages envoyés
        $this->processMessageCollection(
            $user->getSentMessages(),
            function (Message $message) use ($anonymousUser) {
                $message->setSender($anonymousUser);
            },
            function (Message $message) {
                $this->em->remove($message);
            },
            $user
        );

        // Pour les messages reçus
        $this->processMessageCollection(
            $user->getReceivedMessages(),
            function (Message $message) use ($anonymousUser) {
                $message->setReceiver($anonymousUser);
            },
            function (Message $message) {
                $this->em->remove($message);
            },
            $user
        );
    }

    private function processMessageCollection(
        iterable $messages,
        callable $anonymizeAction,
        callable $deleteAction,
        User $originalUser
    ): void {
        foreach ($messages as $message) {
            $counterpart = ($message->getSender() === $originalUser)
                ? $message->getReceiver()
                : $message->getSender();

            $counterpart->isAnonymous()
                ? $deleteAction($message)
                : $anonymizeAction($message);
        }
    }

    private function processBlogPosts(User $user, User $anonymousUser): void
    {
        foreach ($user->getBlogPosts() as $post) {
            $post->setAuthor($anonymousUser);
        }
    }
}
