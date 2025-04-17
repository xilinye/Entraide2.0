<?php

namespace App\Command;

use App\Entity\User;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:promote-admin',
    description: 'Promouvoir un utilisateur en administrateur'
)]
class PromoteAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserManager $userManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Promouvoir un utilisateur en administrateur')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                "Email de l'utilisateur"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $input->getArgument('email')]);

        if (!$user) {
            $output->writeln('<error>Utilisateur non trouvé</error>');
            return Command::FAILURE;
        }

        try {
            $this->userManager->promoteToAdmin($user);
            $output->writeln('<info>Administrateur créé avec succès</info>');
        } catch (RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
