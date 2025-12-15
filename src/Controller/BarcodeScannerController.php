<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\PersonRepository;
use App\Repository\UserBookRepository;
use App\Service\GoogleBookService;
use App\Entity\UserBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BarcodeScannerController extends AbstractController
{
    #[Route('/barcode/scanner', name: 'app_barcode_scanner')]
    public function index(): Response
    {
        return $this->render('barcode_scanner/index.html.twig');
    }

    #[Route('/barcode/scanner/search', name: 'app_barcode_scanner_search', methods: ['POST'])]
    public function search(
        Request $request,
        GoogleBookService $googleBookService,
        ValidatorInterface $validator,
        BookRepository $bookRepository
    ): JsonResponse {
        $isbn = $request->getPayload()->get('isbn');

        if (!$isbn) {
            return $this->json([
                'error' => 'ISBN is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate ISBN
        $constraint = new Assert\Isbn();
        $violations = $validator->validate($isbn, $constraint);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            return $this->json([
                'error' => 'Invalid ISBN',
                'details' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        // Clean ISBN (remove spaces and dashes)
        $cleanIsbn = str_replace(['-', ' '], '', $isbn);

        // 1) Try to find the book locally first
        $book = $bookRepository->findOneByIsbn13($cleanIsbn);

        // 2) If not found locally, search Google Books
        if ($book === null) {
            $book = $googleBookService->searchByIsbn($cleanIsbn);
        }

        if ($book === null) {
            return $this->json([
                'error' => 'No book found for this ISBN'
            ], Response::HTTP_NOT_FOUND);
        }

        // Return book data
        return $this->json([
            'title' => $book->getTitle(),
            'subtitle' => $book->getSubtitle(),
            'authors' => array_map(
                fn($author) => $author->getDisplayName(),
                $book->getAuthors()->toArray()
            ),
            'publisher' => $book->getPublisher(),
            'publicationDate' => $book->getPublicationDate()?->format('Y-m-d'),
            'language' => $book->getLanguage(),
            'summary' => $book->getSummary(),
            'genres' => $book->getGenres(),
            'pageCount' => $book->getPageCount(),
            'coverImageUrl' => $book->getCoverImageUrl(),
            'isbn13' => $book->getIsbn13(),
        ], Response::HTTP_OK);
    }

    #[Route('/barcode/scanner/check', name: 'app_barcode_scanner_check', methods: ['POST'])]
    public function check(
        Request $request,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository
    ): JsonResponse {
        $isbn = $request->getPayload()->get('isbn');

        if (!$isbn) {
            return $this->json([
                'error' => 'ISBN is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'error' => 'Authentication required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Clean ISBN (remove spaces and dashes)
        $cleanIsbn = str_replace(['-', ' '], '', $isbn);

        // Check if book exists in database
        $book = $bookRepository->findOneByIsbn13($cleanIsbn);

        if (!$book) {
            return $this->json([
                'exists' => false,
                'owned' => false
            ]);
        }

        $owned = (bool) $userBookRepository->findOneBy([
            'user' => $user,
            'book' => $book,
        ]);

        return $this->json([
            'exists' => true,
            'owned' => $owned,
            'book' => [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'isbn13' => $book->getIsbn13()
            ]
        ]);
    }

    #[Route('/barcode/scanner/save', name: 'app_barcode_scanner_save', methods: ['POST'])]
    public function save(
        Request $request,
        GoogleBookService $googleBookService,
        BookRepository $bookRepository,
        PersonRepository $personRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserBookRepository $userBookRepository
    ): JsonResponse {
        $isbn = $request->getPayload()->get('isbn');

        if (!$isbn) {
            return $this->json([
                'error' => 'ISBN is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate ISBN
        $constraint = new Assert\Isbn();
        $violations = $validator->validate($isbn, $constraint);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            return $this->json([
                'error' => 'Invalid ISBN',
                'details' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'error' => 'Authentication required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Clean ISBN
        $cleanIsbn = str_replace(['-', ' '], '', $isbn);

        // Find or create the Book
        $book = $bookRepository->findOneByIsbn13($cleanIsbn);
        $wasCreated = false;

        if (!$book) {
            // Search for the book on Google Books
            $book = $googleBookService->searchByIsbn($cleanIsbn);

            if ($book === null) {
                return $this->json([
                    'error' => 'No book found for this ISBN'
                ], Response::HTTP_NOT_FOUND);
            }

            try {
                // Handle authors - persist them separately
                $authors = $book->getAuthors()->toArray();
                $book->getAuthors()->clear();

                foreach ($authors as $author) {
                    $persistedAuthor = $personRepository->findOrCreateByDisplayName($author->getDisplayName());
                    $book->addAuthor($persistedAuthor);
                }

                // Persist the book
                $entityManager->persist($book);
                $entityManager->flush();
                $wasCreated = true;
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Failed to save book',
                    'message' => $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Link the book to the current user if not already linked
        $existingLink = $userBookRepository->findOneBy([
            'user' => $user,
            'book' => $book,
        ]);

        if (!$existingLink) {
            $userBook = new UserBook();
            $userBook->setUser($user);
            $userBook->setBook($book);
            $entityManager->persist($userBook);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'book' => [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'isbn13' => $book->getIsbn13()
            ]
        ], $wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
