<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;

class UserAnonymizer
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    public function anonymize(User $user): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        // Messages envoyés
        foreach ($user->getSentMessages() as $message) {
            if ($message->getReceiver()->getRoles() === ['ROLE_ANONYMOUS']) {
                $this->em->remove($message);
            } else {
                $message->setSender($anonymousUser);
            }
        }


        // Messages reçus
        foreach ($user->getReceivedMessages() as $message) {
            if ($message->getSender()->getRoles() === ['ROLE_ANONYMOUS']) {
                $this->em->remove($message);
            } else {
                $message->setReceiver($anonymousUser);
            }
        }

        // Blog posts
        foreach ($user->getBlogPosts() as $post) {
            $post->setAuthor($anonymousUser);
        }

        $this->em->flush();
    }
}
