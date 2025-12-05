<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Find a book by ISBN-13
     */
    public function findOneByIsbn13(string $isbn13): ?Book
    {
        return $this->findOneBy(['isbn13' => $isbn13]);
    }

    /**
     * Save a book entity
     */
    public function save(Book $book, bool $flush = true): void
    {
        $this->getEntityManager()->persist($book);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
