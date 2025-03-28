<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PromoteAdminCommand extends Command
{
    protected static $defaultName = 'app:promote-admin';

    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:promote-admin')
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

        $roles = $user->getRoles();
        
        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles(array_unique($roles));
            $this->em->flush();
            $output->writeln('<info>Administrateur créé avec succès</info>');
        } else {
            $output->writeln('<comment>L\'utilisateur est déjà administrateur</comment>');
        }

        return Command::SUCCESS;
    }
}