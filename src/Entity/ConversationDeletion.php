<?php

namespace App\Entity;

use App\Repository\ConversationDeletionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationDeletionRepository::class)]
class ConversationDeletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'conversationDeletions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null; // Utilisateur qui supprime

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $otherUser = null; // Participant de la conversation

    #[ORM\Column]
    private ?\DateTimeImmutable $deletedAt = null; // Date de suppression

    #[ORM\Column(length: 255)]
    private ?string $conversationTitle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOtherUser(): ?User
    {
        return $this->otherUser;
    }

    public function setOtherUser(?User $otherUser): static
    {
        $this->otherUser = $otherUser;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setDeletedAtValue(): void
    {
        if ($this->deletedAt === null) {
            $this->deletedAt = new \DateTimeImmutable();
        }
    }

    public function getConversationTitle(): ?string
    {
        return $this->conversationTitle;
    }

    public function setConversationTitle(string $conversationTitle): static
    {
        $this->conversationTitle = $conversationTitle;
        return $this;
    }
}
