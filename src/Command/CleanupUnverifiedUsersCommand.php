<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:cleanup-unverified-users',
    description: 'Supprime les utilisateurs non vérifiés'
)]
class CleanupUnverifiedUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Supprime les utilisateurs non vérifiés après 24h');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredUsers = $this->userRepository->findExpiredUnverifiedUsers();

        foreach ($expiredUsers as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('Supprimé %d compte(s) expiré(s)', count($expiredUsers)));

        return Command::SUCCESS;
    }
}