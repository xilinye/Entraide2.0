<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:clean-reset-tokens',
    description: 'Supprime les tokens expirés générés par mot de passe oublié'
)]
class CleanResetTokensCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.resetTokenExpiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
        }

        $this->em->flush();

        $output->writeln(sprintf('Nettoyage terminé : %d tokens expirés supprimés', count($users)));
        return Command::SUCCESS;
    }
}
