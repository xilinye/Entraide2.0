<?php

namespace App\Service;

use App\Entity\{User, Message, Rating};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class UserAnonymizer
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
        private readonly Filesystem $filesystem
    ) {}

    // src/Service/UserAnonymizer.php

    public function anonymizeParticipations(User $user): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        // Transfert des participations
        $this->transferParticipations($user, $anonymousUser);

        $this->em->flush();
    }

    private function transferParticipations(User $user, User $anonymousUser): void
    {
        // Messages
        $this->em->createQuery('UPDATE App\Entity\Message m SET m.sender = :anon WHERE m.sender = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();

        $this->em->createQuery('UPDATE App\Entity\Message m SET m.receiver = :anon WHERE m.receiver = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();

        // Réponses aux forums
        $this->em->createQuery('UPDATE App\Entity\ForumResponse fr SET fr.author = :anon WHERE fr.author = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();

        // Participation aux événements
        foreach ($user->getAttendedEvents() as $event) {
            $event->removeAttendee($user);
            $event->addAttendee($anonymousUser);
        }

        // Évaluations
        $this->em->createQuery('UPDATE App\Entity\Rating r SET r.rater = :anon WHERE r.rater = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();

        $this->em->createQuery('UPDATE App\Entity\Rating r SET r.ratedUser = :anon WHERE r.ratedUser = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();

        // Suppressions de conversations
        $this->em->createQuery('UPDATE App\Entity\ConversationDeletion cd SET cd.user = :anon WHERE cd.user = :user')
            ->setParameters(['anon' => $anonymousUser, 'user' => $user])
            ->execute();
    }
}
