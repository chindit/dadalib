<?php

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    /**
     * Find or create a person by display name
     */
    public function findOrCreateByDisplayName(string $displayName): Person
    {
        $person = $this->findOneBy(['displayName' => $displayName]);

        if (!$person) {
            $person = new Person();
            $person->setDisplayName($displayName);
            $this->getEntityManager()->persist($person);
        }

        return $person;
    }

    /**
     * Save a person entity
     */
    public function save(Person $person, bool $flush = true): void
    {
        $this->getEntityManager()->persist($person);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
