<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre ne peut pas être vide")]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu ne peut pas être vide")]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "L'auteur est obligatoire")]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isOpen = true;

    #[ORM\OneToMany(targetEntity: ForumResponse::class, mappedBy: 'forum', orphanRemoval: true)]
    private Collection $responses;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    private $imageFile;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->responses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        if ($this->author !== $author) {
            $this->author?->removeForum($this);
            $this->author = $author;
            $author?->addForum($this);
        }
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        if ($this->category === $category) {
            return $this;
        }

        $oldCategory = $this->category;
        $this->category = $category;

        // Retirer de l'ancienne catégorie
        if ($oldCategory !== null) {
            $oldCategory->removeForum($this);
        }

        // Ajouter à la nouvelle catégorie
        if ($category !== null && !$category->getForums()->contains($this)) {
            $category->addForum($this);
        }

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

    public function isOpen(): ?bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }


    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(ForumResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setForum($this);
        }

        return $this;
    }

    public function removeResponse(ForumResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getForum() === $this) {
                $response->setForum(null);
            }
        }

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;
        return $this;
    }

    public function getImageFile()
    {
        return $this->imageFile;
    }

    public function setImageFile($imageFile): static
    {
        $this->imageFile = $imageFile;
        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Nouveau forum';
    }
}
