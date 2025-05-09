<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, UserInterface};
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudonyme est déjà utilisé')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: false)]
    #[Assert\NotBlank(message: 'Le pseudo est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'Caractères autorisés : lettres, chiffres et underscores'
    )]
    private ?string $pseudo = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'Format d\'email invalide')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registrationToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\ManyToMany(targetEntity: Skill::class, inversedBy: 'users', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'user_skill')]
    private Collection $skills;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $sentMessages;
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'receiver')]
    private Collection $receivedMessages;

    #[ORM\OneToMany(targetEntity: BlogPost::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $blogPosts;

    #[ORM\OneToMany(targetEntity: ConversationDeletion::class, mappedBy: 'user')]
    private Collection $conversationDeletions;

    #[ORM\OneToMany(targetEntity: Forum::class, mappedBy: 'author')]
    private Collection $forums;

    #[ORM\OneToMany(targetEntity: ForumResponse::class, mappedBy: 'author')]
    private Collection $forumResponses;

    #[ORM\OneToMany(mappedBy: 'organizer', targetEntity: Event::class)]
    private Collection $organizedEvents;

    #[ORM\ManyToMany(targetEntity: Event::class, inversedBy: 'attendees')]
    private Collection $attendedEvents;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $profileImage = null;

    #[ORM\OneToMany(mappedBy: 'ratedUser', targetEntity: Rating::class)]
    private Collection $ratingsReceived;

    #[ORM\OneToMany(mappedBy: 'rater', targetEntity: Rating::class)]
    private Collection $ratingsGiven;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tokenExpiresAt = $this->createdAt->modify('+24 hours');
        $this->registrationToken = bin2hex(random_bytes(32));
        $this->skills = new ArrayCollection();
        $this->sentMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
        $this->blogPosts = new ArrayCollection();
        $this->conversationDeletions = new ArrayCollection();
        $this->forums = new ArrayCollection();
        $this->forumResponses = new ArrayCollection();
        $this->organizedEvents = new ArrayCollection();
        $this->attendedEvents = new ArrayCollection();
        $this->ratingsReceived = new ArrayCollection();
        $this->ratingsGiven = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->pseudo;
    }

    public function getRoles(): array
    {
        return array_unique([...$this->roles, 'ROLE_USER']);
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getRegistrationToken(): string
    {
        if (null === $this->registrationToken) {
            $this->registrationToken = bin2hex(random_bytes(32));
        }
        return $this->registrationToken;
    }

    public function setRegistrationToken(?string $registrationToken): static
    {
        $this->registrationToken = $registrationToken;
        return $this;
    }

    public function isTokenExpired(): bool
    {
        return $this->tokenExpiresAt < new \DateTimeImmutable();
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
            $skill->addUser($this);
        }
        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        if ($this->skills->removeElement($skill)) {
            $skill->removeUser($this);
        }
        return $this;
    }

    public function getSentMessages(): Collection
    {
        return $this->sentMessages;
    }

    public function getReceivedMessages(): Collection
    {
        return $this->receivedMessages;
    }

    public function addSentMessage(Message $message): static
    {
        if (!$this->sentMessages->contains($message)) {
            $this->sentMessages->add($message);
            $message->setSender($this);
        }
        return $this;
    }

    public function removeSentMessage(Message $message): static
    {
        if ($this->sentMessages->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }
    public function addReceivedMessage(Message $message): static
    {
        if (!$this->receivedMessages->contains($message)) {
            $this->receivedMessages->add($message);
            $message->setReceiver($this);
        }
        return $this;
    }

    public function removeReceivedMessage(Message $message): static
    {
        if ($this->receivedMessages->removeElement($message)) {
            if ($message->getReceiver() === $this) {
                $message->setReceiver(null);
            }
        }
        return $this;
    }

    public function getBlogPosts(): Collection
    {
        return $this->blogPosts;
    }

    public function addBlogPost(BlogPost $blogPost): static
    {
        if (!$this->blogPosts->contains($blogPost)) {
            $this->blogPosts->add($blogPost);
            $blogPost->setAuthor($this);
        }

        return $this;
    }

    public function removeBlogPost(BlogPost $blogPost): static
    {
        if ($this->blogPosts->removeElement($blogPost)) {
            if ($blogPost->getAuthor() === $this) {
                $blogPost->setAuthor(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->pseudo ?? 'Nouvel utilisateur';
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function isResetTokenExpired(): bool
    {
        return $this->resetTokenExpiresAt < new \DateTimeImmutable();
    }

    public function getSkillsByCategory(): array
    {
        $grouped = [];
        foreach ($this->skills as $skill) {
            $categoryName = $skill->getCategory()?->getName() ?? 'Non classé';
            $grouped[$categoryName][] = $skill;
        }
        ksort($grouped);
        return $grouped;
    }

    public function hasSkill(Skill $skill): bool
    {
        return $this->skills->contains($skill);
    }

    public function getConversationDeletions(): Collection
    {
        return $this->conversationDeletions;
    }

    public function addConversationDeletion(ConversationDeletion $conversationDeletion): static
    {
        if (!$this->conversationDeletions->contains($conversationDeletion)) {
            $this->conversationDeletions->add($conversationDeletion);
            $conversationDeletion->setUser($this);
        }

        return $this;
    }

    public function removeConversationDeletion(ConversationDeletion $conversationDeletion): static
    {
        if ($this->conversationDeletions->removeElement($conversationDeletion)) {
            if ($conversationDeletion->getUser() === $this) {
                $conversationDeletion->setUser(null);
            }
        }

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function isAnonymous(): bool
    {
        return in_array('ROLE_ANONYMOUS', $this->getRoles(), true);
    }

    public function getForums(): Collection
    {
        return $this->forums;
    }

    public function addForum(Forum $forum): static
    {
        if (!$this->forums->contains($forum)) {
            $this->forums->add($forum);
            $forum->setAuthor($this);
        }

        return $this;
    }

    public function removeForum(Forum $forum): static
    {
        if ($this->forums->removeElement($forum)) {
            if ($forum->getAuthor() === $this) {
                $forum->setAuthor(null);
            }
        }

        return $this;
    }

    public function getForumResponses(): Collection
    {
        return $this->forumResponses;
    }

    public function addForumResponse(ForumResponse $forumResponse): static
    {
        if (!$this->forumResponses->contains($forumResponse)) {
            $this->forumResponses->add($forumResponse);
            $forumResponse->setAuthor($this);
        }

        return $this;
    }

    public function removeForumResponse(ForumResponse $forumResponse): static
    {
        if ($this->forumResponses->removeElement($forumResponse)) {
            if ($forumResponse->getAuthor() === $this) {
                $forumResponse->setAuthor(null);
            }
        }

        return $this;
    }

    public function getOrganizedEvents(): Collection
    {
        return $this->organizedEvents;
    }

    public function addOrganizedEvent(Event $organizedEvent): static
    {
        if (!$this->organizedEvents->contains($organizedEvent)) {
            $this->organizedEvents->add($organizedEvent);
            $organizedEvent->setOrganizer($this);
        }

        return $this;
    }

    public function removeOrganizedEvent(Event $organizedEvent): static
    {
        if ($this->organizedEvents->removeElement($organizedEvent)) {
            // Définissez le côté propriétaire à null (sauf si déjà changé)
            if ($organizedEvent->getOrganizer() === $this) {
                $organizedEvent->setOrganizer(null);
            }
        }

        return $this;
    }

    public function getAttendedEvents(): Collection
    {
        return $this->attendedEvents;
    }

    public function addAttendedEvent(Event $event): self
    {
        if (!$this->attendedEvents->contains($event)) {
            $this->attendedEvents->add($event);
            $event->addAttendee($this);
        }
        return $this;
    }

    public function removeAttendedEvent(Event $event): self
    {
        if ($this->attendedEvents->removeElement($event)) {
            $event->removeAttendee($this);
        }
        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): self
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function getRatingsReceived(): Collection
    {
        return $this->ratingsReceived;
    }

    public function getAverageRating(): float
    {
        $total = 0;
        $count = 0;

        foreach ($this->ratingsReceived as $rating) {
            $total += $rating->getScore();
            $count++;
        }

        return $count > 0 ? round($total / $count, 1) : 0;
    }

    public function getRatingDetails(): array
    {
        $details = [
            'blog' => ['total' => 0, 'average' => 0],
            'event' => ['total' => 0, 'average' => 0],
            'forum' => ['total' => 0, 'average' => 0]
        ];

        foreach ($this->ratingsReceived as $rating) {
            $type = 'blog';
            if ($rating->getEvent()) $type = 'event';
            elseif ($rating->getForumResponse()) $type = 'forum';

            $details[$type]['total']++;
            $details[$type]['average'] += $rating->getScore();
        }

        foreach ($details as &$type) {
            if ($type['total'] > 0) {
                $type['average'] = round($type['average'] / $type['total'], 1);
            }
        }

        return $details;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getRatingsGiven(): Collection
    {
        return $this->ratingsGiven;
    }
}
