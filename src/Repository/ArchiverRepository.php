<?php

namespace App\Repository;

use App\Entity\Archiver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @method Archiver|null find($id, $lockMode = null, $lockVersion = null)
 * @method Archiver|null findOneBy(array $criteria, array $orderBy = null)
 * @method Archiver[]    findAll()
 * @method Archiver[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Archiver>
 */
class ArchiverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Archiver::class);
    }

    public function findOneByNameOrId(string $value): ?Archiver
    {
        $valueId = $value;
        try {
            $valueId = (new Uuid($value))->toBinary();
        } catch (\InvalidArgumentException) {
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.name = :value or s.id = :value_id')
            ->setParameter('value', $value)
            ->setParameter('value_id', $valueId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
