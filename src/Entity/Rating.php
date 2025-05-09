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

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'ratings')]
    private ?BlogPost $blogPost = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: ForumResponse::class)]
    private ?ForumResponse $forumResponse = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être entre 1 et 5.')]
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
        if (count($targets) !== 1) {
            $context->buildViolation('Une note doit être associée à exactement un élément (article, événement ou réponse).')
                ->atPath('blogPost')
                ->addViolation();
        }
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
        if ($this->blogPost !== $blogPost) {
            $oldBlogPost = $this->blogPost;
            $this->blogPost = $blogPost;

            if ($oldBlogPost !== null) {
                $oldBlogPost->removeRating($this);
            }

            if ($blogPost !== null) {
                $blogPost->addRating($this);
            }
        }

        return $this;
    }
    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        if ($this->event !== $event) {
            $this->event = $event;
            if ($event !== null) {
                $event->addRating($this);
            }
        }
        return $this;
    }

    public function getForumResponse(): ?ForumResponse
    {
        return $this->forumResponse;
    }

    public function setForumResponse(?ForumResponse $forumResponse): self
    {
        // Si la réponse est la même, on ne fait rien
        if ($this->forumResponse === $forumResponse) {
            return $this;
        }

        // On conserve l'ancienne réponse
        $oldForumResponse = $this->forumResponse;
        $this->forumResponse = $forumResponse;

        // On se retire de l'ancienne réponse
        if ($oldForumResponse !== null) {
            $oldForumResponse->removeRating($this);
        }

        // On s'ajoute à la nouvelle réponse
        if ($forumResponse !== null) {
            $forumResponse->addRating($this);
        }

        return $this;
    }
}
