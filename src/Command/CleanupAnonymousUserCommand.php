<?php

namespace App\Command;

use App\Entity\{Message, User, BlogPost, ConversationDeletion};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-anonymous-user',
    description: 'Supprime l\'utilisateur anonyme si tous les utilisateurs liés sont supprimés'
)]
class CleanupAnonymousUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anonymousUser = $this->userRepository->findAnonymousUser();

        if (!$anonymousUser) {
            $output->writeln('Aucun utilisateur anonyme trouvé.');
            return Command::SUCCESS;
        }

        // Supprime les messages orphelins où les deux utilisateurs sont supprimés
        $this->cleanupOrphanMessages($anonymousUser, $output);

        // Vérifie les relations restantes
        $remainingRelations = $this->checkRemainingRelations($anonymousUser);

        if ($remainingRelations === 0) {
            $this->em->remove($anonymousUser);
            $this->em->flush();
            $output->writeln('Utilisateur anonyme supprimé avec succès.');
        } else {
            $output->writeln([
                "L'utilisateur anonyme a encore des données associées :",
                "- Relations actives : $remainingRelations",
                "Suppression annulée."
            ]);
        }

        return Command::SUCCESS;
    }

    private function cleanupOrphanMessages(User $anonymousUser, OutputInterface $output): void
    {
        $messageRepo = $this->em->getRepository(Message::class);

        // Trouve les messages entre utilisateurs supprimés
        $orphanMessages = $messageRepo->createQueryBuilder('m')
            ->where('m.sender = :anonymous AND m.receiver = :anonymous')
            ->setParameter('anonymous', $anonymousUser)
            ->getQuery()
            ->getResult();

        if (count($orphanMessages) > 0) {
            foreach ($orphanMessages as $message) {
                $this->em->remove($message);
            }
            $this->em->flush();
            $output->writeln(sprintf('Supprimé %d messages orphelins', count($orphanMessages)));
        }
    }

    private function checkRemainingRelations(User $anonymousUser): int
    {
        $count = 0;

        // Messages avec au moins un utilisateur actif
        $count += $this->em->getRepository(Message::class)->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.sender = :user OR m.receiver = :user')
            ->andWhere('m.sender != m.receiver') // Exclut les messages déjà nettoyés
            ->setParameter('user', $anonymousUser)
            ->getQuery()
            ->getSingleScalarResult();

        // Autres relations
        $count += $this->em->getRepository(BlogPost::class)->count(['author' => $anonymousUser]);
        $count += $this->em->getRepository(ConversationDeletion::class)->count(['user' => $anonymousUser]);
        $count += $this->em->getRepository(ConversationDeletion::class)->count(['otherUser' => $anonymousUser]);

        return $count;
    }
}
