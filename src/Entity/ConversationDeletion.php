<?php

namespace App\Entity;

use App\Repository\ConversationDeletionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ConversationDeletionRepository::class)]
class ConversationDeletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'conversationDeletions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    private ?User $user = null; // Utilisateur qui supprime

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'autre utilisateur est obligatoire.')]
    private ?User $otherUser = null; // Participant de la conversation

    #[ORM\Column]
    private ?\DateTimeImmutable $deletedAt = null; // Date de suppression

    #[ORM\Column(length: 255)]
    #[Assert\NotNull(message: 'Le titre de la conversation est obligatoire.')]
    #[Assert\Length(max: 255)]
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
        if ($this->user === $user) {
            return $this;
        }

        $oldUser = $this->user;
        $this->user = $user;

        if ($oldUser !== null) {
            $oldUser->removeConversationDeletion($this);
        }

        if ($user !== null && !$user->getConversationDeletions()->contains($this)) {
            $user->addConversationDeletion($this);
        }

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

    #[Assert\Callback]
    public function validateUsers(ExecutionContextInterface $context)
    {
        if ($this->user && $this->otherUser) {
            // Compare à la fois les instances et les IDs
            if ($this->user === $this->otherUser || $this->user->getId() === $this->otherUser->getId()) {
                $context->buildViolation('Une conversation ne peut pas être avec soi-même')
                    ->atPath('otherUser')
                    ->addViolation();
            }
        }
    }
}
