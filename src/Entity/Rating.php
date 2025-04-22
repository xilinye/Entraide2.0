<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $rater;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ratingsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    private User $ratedUser;

    #[ORM\ManyToOne(targetEntity: BlogPost::class)]
    private ?BlogPost $blogPost = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: ForumResponse::class)]
    private ?ForumResponse $forumResponse = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Veuillez sélectionner une note.")]
    #[Assert\Range(min: 1, max: 5)]
    private int $score = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context)
    {
        $targets = array_filter([
            $this->blogPost,
            $this->event,
            $this->forumResponse
        ]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRater(): User
    {
        return $this->rater;
    }

    public function setRater(User $rater): static
    {
        $this->rater = $rater;
        return $this;
    }

    public function getRatedUser(): User
    {
        return $this->ratedUser;
    }

    public function setRatedUser(User $ratedUser): static
    {
        $this->ratedUser = $ratedUser;
        return $this;
    }

    public function getBlogPost(): ?BlogPost
    {
        return $this->blogPost;
    }

    public function setBlogPost(?BlogPost $blogPost): static
    {
        $this->blogPost = $blogPost;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getForumResponse(): ?ForumResponse
    {
        return $this->forumResponse;
    }

    public function setForumResponse(?ForumResponse $forumResponse): static
    {
        $this->forumResponse = $forumResponse;
        return $this;
    }
}
