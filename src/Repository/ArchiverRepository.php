<?php

namespace App\Repository;

use App\Entity\Archiver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Archiver|null find($id, $lockMode = null, $lockVersion = null)
 * @method Archiver|null findOneBy(array $criteria, array $orderBy = null)
 * @method Archiver[]    findAll()
 * @method Archiver[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArchiverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Archiver::class);
    }

    public function findOneByNameOrId($value): ?Archiver
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.name = :val or s.id = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
