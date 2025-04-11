<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
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
                'CASE WHEN s.id < r.id THEN s.id ELSE r.id END AS user1',
                'CASE WHEN s.id < r.id THEN r.id ELSE s.id END AS user2',
                'MAX(m.createdAt) as last_message_date',
                'MAX(m.title) as last_title',
                'CASE WHEN cd.user IS NOT NULL THEN \'Anonymous\' ELSE MAX(CASE WHEN m.sender = :user THEN r.pseudo ELSE s.pseudo END) END as other_user_pseudo',
                'MAX(CASE WHEN m.sender = :user THEN r.id ELSE s.id END) as other_user_id',
                'SUM(CASE WHEN m.receiver = :user AND m.isRead = false THEN 1 ELSE 0 END) as unread_count'
            ])
            ->innerJoin('m.sender', 's')
            ->innerJoin('m.receiver', 'r')
            ->leftJoin(
                'App\Entity\ConversationDeletion',
                'cd',
                'WITH',
                '(cd.user = CASE WHEN m.sender = :user THEN r ELSE s END AND cd.otherUser = :user)'
            )
            ->where('(m.sender = :user OR m.receiver = :user)')
            ->andWhere('(cd_deleted.user IS NULL)') // Exclure les conversations supprimées par l'utilisateur
            ->leftJoin(
                'App\Entity\ConversationDeletion',
                'cd_deleted',
                'WITH',
                'cd_deleted.user = :user AND (cd_deleted.otherUser = s OR cd_deleted.otherUser = r)'
            )
            ->groupBy('user1, user2')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findConversationBetweenUsers(User $user1, User $user2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
            ->andWhere('m.sender != :anonymousUser OR m.receiver != :anonymousUser') // Exclure les conversations entièrement anonymes
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->setParameter('anonymousUser', $this->getEntityManager()->getRepository(User::class)->findAnonymousUser())
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
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
