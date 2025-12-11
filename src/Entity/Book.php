<?php

namespace App\Entity;

use App\Enum\BookFormat;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['isbn13'], message: 'This ISBN-13 is already used.')]
class Book
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\ManyToMany(targetEntity: Person::class)]
    #[ORM\JoinTable(name: 'book_authors')]
    private Collection $authors;

    #[ORM\ManyToMany(targetEntity: Person::class)]
    #[ORM\JoinTable(name: 'book_contributors')]
    private Collection $contributors;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seriesName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seriesNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $publicationDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $edition = null;

    #[ORM\Column(length: 35)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z]{2,3}(-[A-Za-z0-9]{2,8})*$/',
        message: 'Use a valid BCP-47 language tag, e.g., fr, fr-CA, en-GB.'
    )]
    private string $language = 'fr';

    #[ORM\Column(length: 35, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z]{2,3}(-[A-Za-z0-9]{2,8})*$/',
        message: 'Use a valid BCP-47 language tag, e.g., fr, fr-CA, en-GB.'
    )]
    private ?string $originalLanguage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'json')]
    /** @var string[] */
    private array $genres = [];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ageRating = null; // or readingLevel

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $pageCount = null;

    #[ORM\Column(type: 'string', enumType: BookFormat::class)]
    private BookFormat $format = BookFormat::PAPERBACK;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url]
    private ?string $coverImageUrl = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    #[Assert\Isbn(type: Assert\Isbn::ISBN_13)]
    private ?string $isbn13 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->contributors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Person $person): self
    {
        if (!$this->authors->contains($person)) {
            $this->authors->add($person);
        }
        return $this;
    }

    public function removeAuthor(Person $person): self
    {
        $this->authors->removeElement($person);
        return $this;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getContributors(): Collection
    {
        return $this->contributors;
    }

    public function addContributor(Person $person): self
    {
        if (!$this->contributors->contains($person)) {
            $this->contributors->add($person);
        }
        return $this;
    }

    public function removeContributor(Person $person): self
    {
        $this->contributors->removeElement($person);
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

    public function getPublicationDate(): ?\DateTimeImmutable
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?\DateTimeImmutable $publicationDate): self
    {
        $this->publicationDate = $publicationDate;
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(?string $originalLanguage): self
    {
        $this->originalLanguage = $originalLanguage;
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
     * @return string[]
     */
    public function getGenres(): array
    {
        return $this->genres;
    }

    /**
     * @param string[] $genres
     */
    public function setGenres(array $genres): self
    {
        $this->genres = array_values($genres);
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

    public function getFormat(): BookFormat
    {
        return $this->format;
    }

    public function setFormat(BookFormat $format): self
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

    public function getIsbn13(): ?string
    {
        return $this->isbn13;
    }

    public function setIsbn13(?string $isbn13): self
    {
        $this->isbn13 = $isbn13;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
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
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
