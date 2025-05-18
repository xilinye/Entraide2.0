<?php

namespace App\Command;

use App\Entity\{Message, User, BlogPost, Forum, ForumResponse, Event, Rating};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-anonymous-user',
    description: 'Nettoie l\'utilisateur anonyme et ses relations orphelines'
)]
class CleanupAnonymousUserCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Exécuter en mode test sans modifications'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        try {
            $anonymousUser = $this->userRepository->findAnonymousUser();

            if (!$anonymousUser) {
                $io->success('Aucun utilisateur anonyme trouvé.');
                return Command::SUCCESS;
            }

            if (!$anonymousUser->hasRole('ROLE_ANONYMOUS')) {
                $io->error('L\'utilisateur trouvé n\'a pas le rôle anonyme');
                return Command::FAILURE;
            }

            $io->section('Nettoyage des données orphelines');
            $userId = $anonymousUser->getId();

            // Étape 1 : Nettoyage des messages
            $processedMessages = $this->cleanupOrphanMessages($anonymousUser, $dryRun);
            $io->text(sprintf('Messages traités : %d', count($processedMessages)));

            if (!$dryRun) {
                $this->em->clear();
                $anonymousUser = $this->userRepository->find($userId); // Recharger l'entité fraîche
            }

            // Étape 2 : Vérification des relations
            $remainingRelations = $this->checkRemainingRelations(
                $anonymousUser,
                $dryRun ? $processedMessages : []
            );

            if ($remainingRelations > 0) {
                $io->error([
                    "Impossible de supprimer l'utilisateur anonyme",
                    sprintf("%d relations actives détectées", $remainingRelations)
                ]);
                return Command::FAILURE;
            }

            // Étape 3 : Suppression finale
            if (!$dryRun) {
                $this->em->transactional(function () use ($anonymousUser) {
                    $this->em->remove($anonymousUser);
                    $this->em->flush();
                });
                $io->success('Utilisateur anonyme supprimé avec succès');
            } else {
                $io->success('[DRY RUN] Opération simulée avec succès');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Erreur lors du nettoyage',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function cleanupOrphanMessages(User $user, bool $dryRun): array
    {
        $processedIds = [];
        $messageRepo = $this->em->getRepository(Message::class);

        $query = $messageRepo->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.sender = :user AND m.receiver = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->setHydrationMode(\Doctrine\ORM\Query::HYDRATE_SCALAR);

        $iterableResult = $query->toIterable();

        foreach ($iterableResult as $message) {
            $processedIds[] = $message['id'];

            if (!$dryRun) {
                $this->em->getConnection()->executeStatement(
                    'DELETE FROM message WHERE id = ?',
                    [$message['id']]
                );
            }

            if (count($processedIds) % self::BATCH_SIZE === 0 && !$dryRun) {
                $this->em->clear(Message::class);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $processedIds;
    }
    private function checkRemainingRelations(User $user, array $excludedMessageIds = []): int
    {
        $qbMessages = $this->em->getRepository(Message::class)
            ->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user);

        if (!empty($excludedMessageIds)) {
            $qbMessages->andWhere('m.id NOT IN (:excluded)')
                ->setParameter('excluded', $excludedMessageIds);
        } else {
            $qbMessages->andWhere('1 = 1');
        }


        $checks = [
            'Messages' => $qbMessages->getQuery()->getSingleScalarResult(),
            'BlogPosts' => $this->em->getRepository(BlogPost::class)
                ->count(['author' => $user]),
            'ForumPosts' => $this->em->getRepository(Forum::class)
                ->count(['author' => $user]),
            'ForumResponses' => $this->em->getRepository(ForumResponse::class)
                ->count(['author' => $user]),
            'OrganizedEvents' => $this->em->getRepository(Event::class)
                ->count(['organizer' => $user]),
            'AttendedEvents' => $this->em->createQueryBuilder()
                ->select('COUNT(1)')
                ->from(Event::class, 'e')
                ->innerJoin('e.attendees', 'a')
                ->where('a = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult(),
            'Ratings' => $this->em->createQueryBuilder()
                ->select('COUNT(1)')
                ->from(Rating::class, 'r')
                ->where('r.rater = :user OR r.ratedUser = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult()
        ];

        return array_sum($checks);
    }
}
