<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\Person;
use App\Enum\BookFormat;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBookService
{
    public function __construct(
        private readonly string $googleBooksApiKey,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * Search for a book by ISBN using Google Books API (REST)
     *
     * @param string $isbn The ISBN to search for (ISBN-10 or ISBN-13)
     * @return Book|null The Book object if found, null otherwise
     */
    public function searchByIsbn(string $isbn): ?Book
    {
        try {
            // Call Google Books API directly via HTTP
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => [
                    'q' => 'isbn:' . $isbn,
                    'key' => $this->googleBooksApiKey,
                    'projection' => 'full',
                    'printType' => 'books'
                ]
            ]);

            $data = $response->toArray();

            dump('=== Google Books API Response ===');
            dump($data);

            if (!isset($data['items']) || empty($data['items'])) {
                return null;
            }

            // Get the first result
            $volumeData = $data['items'][0];
            $volumeInfo = $volumeData['volumeInfo'] ?? [];

            // Create a new Book entity
            $book = new Book();

            // Title
            if (isset($volumeInfo['title'])) {
                $book->setTitle($volumeInfo['title']);
            }

            // Subtitle
            if (isset($volumeInfo['subtitle'])) {
                $book->setSubtitle($volumeInfo['subtitle']);
            }

            // Authors
            if (isset($volumeInfo['authors'])) {
                foreach ($volumeInfo['authors'] as $authorName) {
                    $person = new Person();
                    $person->setDisplayName($authorName);
                    $book->addAuthor($person);
                }
            }

            // Publisher
            if (isset($volumeInfo['publisher'])) {
                $book->setPublisher($volumeInfo['publisher']);
            }

            // Publication date
            if (isset($volumeInfo['publishedDate'])) {
                try {
                    $publicationDate = new \DateTimeImmutable($volumeInfo['publishedDate']);
                    $book->setPublicationDate($publicationDate);
                } catch (\Exception $e) {
                    // If date parsing fails, skip it
                }
            }

            // Language
            if (isset($volumeInfo['language'])) {
                $book->setLanguage($volumeInfo['language']);
            }

            // Description/Summary
            if (isset($volumeInfo['description'])) {
                $book->setSummary($volumeInfo['description']);
            }

            // Categories/Genres
            if (isset($volumeInfo['categories'])) {
                $book->setGenres($volumeInfo['categories']);
            }

            // Page count
            if (isset($volumeInfo['pageCount'])) {
                $book->setPageCount($volumeInfo['pageCount']);
            }

            // Cover image
            if (isset($volumeInfo['imageLinks'])) {
                $imageLinks = $volumeInfo['imageLinks'];
                $imageUrl = null;

                // Try different image sizes (prefer larger)
                if (isset($imageLinks['extraLarge'])) {
                    $imageUrl = $imageLinks['extraLarge'];
                } elseif (isset($imageLinks['large'])) {
                    $imageUrl = $imageLinks['large'];
                } elseif (isset($imageLinks['medium'])) {
                    $imageUrl = $imageLinks['medium'];
                } elseif (isset($imageLinks['small'])) {
                    $imageUrl = $imageLinks['small'];
                } elseif (isset($imageLinks['thumbnail'])) {
                    $imageUrl = $imageLinks['thumbnail'];
                } elseif (isset($imageLinks['smallThumbnail'])) {
                    $imageUrl = $imageLinks['smallThumbnail'];
                }

                // Replace http with https for images
                if ($imageUrl) {
                    $imageUrl = str_replace('http:', 'https:', $imageUrl);
                    $book->setCoverImageUrl($imageUrl);
                }
            }

            // ISBN-13
            if (isset($volumeInfo['industryIdentifiers'])) {
                foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                    if (isset($identifier['type']) && $identifier['type'] === 'ISBN_13') {
                        $book->setIsbn13($identifier['identifier']);
                        break;
                    }
                }
            }

            // Set default format as we can't determine it from Google Books
            $book->setFormat(BookFormat::PAPERBACK);

            return $book;
        } catch (\Exception $e) {
            dump('=== Exception ===');
            dump($e->getMessage());
            return null;
        }
    }
}
