<?php

namespace App\Repository;

use App\Entity\{Message,User};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findConversations(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->select([
                'CASE WHEN m.sender = :user THEN r.id ELSE s.id END AS other_user_id',
                'COALESCE(MAX(CASE WHEN m.sender = :user THEN r.pseudo ELSE s.pseudo END), \'Utilisateur supprimé\') AS other_user_pseudo',
                'MAX(m.title) as last_title',
                'MAX(m.createdAt) as last_message_date',
                'SUM(CASE WHEN m.receiver = :user AND m.isRead = false THEN 1 ELSE 0 END) as unread_count',
                'MAX(cd.deletedAt) as deletion_date'
            ])
            ->innerJoin('m.sender', 's')
            ->innerJoin('m.receiver', 'r')
            ->leftJoin(
                'App\Entity\ConversationDeletion',
                'cd',
                'WITH',
                'cd.user = :user AND cd.otherUser = CASE WHEN m.sender = :user THEN r ELSE s END'
            )
            ->where('m.sender = :user OR m.receiver = :user')
            ->groupBy('other_user_id')
            ->having('MAX(cd.deletedAt) IS NULL OR MAX(m.createdAt) > MAX(cd.deletedAt)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findConversationBetweenUsers(User $currentUser, User $otherUser): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin(
                'App\Entity\ConversationDeletion',
                'cd',
                'WITH',
                'cd.user = :currentUser AND cd.otherUser = :otherUser'
            )
            ->where('(m.sender = :currentUser AND m.receiver = :otherUser) OR (m.sender = :otherUser AND m.receiver = :currentUser)')
            ->andWhere('cd IS NULL OR m.createdAt > cd.deletedAt')
            ->orderBy('m.createdAt', 'ASC');

        $qb->setParameter('currentUser', $currentUser)
            ->setParameter('otherUser', $otherUser);

        return $qb->getQuery()->getResult();
    }

    public function markMessagesAsRead(User $receiver, User $sender): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', true)
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.isRead = false')
            ->setParameter('receiver', $receiver)
            ->setParameter('sender', $sender)
            ->getQuery()
            ->execute();
    }
}
