<?php

namespace App\Entity;

use App\Enum\BookFormat;
use App\Repository\UserBookRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserBookRepository::class)]
#[ORM\Table(name: 'user_book')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_BOOK_OWNER', columns: ['user_id', 'book_id'])]
#[ORM\HasLifecycleCallbacks]
class UserBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userBooks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userBooks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    // Override fields (all optional => null means no override)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seriesName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seriesNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $edition = null;

    #[ORM\Column(length: 35, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z]{2,3}(-[A-Za-z0-9]{2,8})*$/',
        message: 'Use a valid BCP-47 language tag, e.g., fr, fr-CA, en-GB.'
    )]
    private ?string $language = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'json', nullable: true)]
    /** @var string[]|null */
    private ?array $genres = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ageRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $pageCount = null;

    #[ORM\Column(type: 'string', enumType: BookFormat::class, nullable: true)]
    private ?BookFormat $format = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url]
    private ?string $coverImageUrl = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): self
    {
        $this->book = $book;
        return $this;
    }

    public function getSeriesName(): ?string
    {
        return $this->seriesName;
    }

    public function setSeriesName(?string $seriesName): self
    {
        $this->seriesName = $seriesName;
        return $this;
    }

    public function getSeriesNumber(): ?int
    {
        return $this->seriesNumber;
    }

    public function setSeriesNumber(?int $seriesNumber): self
    {
        $this->seriesNumber = $seriesNumber;
        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }

    public function getEdition(): ?string
    {
        return $this->edition;
    }

    public function setEdition(?string $edition): self
    {
        $this->edition = $edition;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getGenres(): ?array
    {
        return $this->genres;
    }

    /**
     * @param string[]|null $genres
     */
    public function setGenres(?array $genres): self
    {
        $this->genres = $genres;
        return $this;
    }

    public function getAgeRating(): ?string
    {
        return $this->ageRating;
    }

    public function setAgeRating(?string $ageRating): self
    {
        $this->ageRating = $ageRating;
        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): self
    {
        $this->pageCount = $pageCount;
        return $this;
    }

    public function getFormat(): ?BookFormat
    {
        return $this->format;
    }

    public function setFormat(?BookFormat $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getCoverImageUrl(): ?string
    {
        return $this->coverImageUrl;
    }

    public function setCoverImageUrl(?string $coverImageUrl): self
    {
        $this->coverImageUrl = $coverImageUrl;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
