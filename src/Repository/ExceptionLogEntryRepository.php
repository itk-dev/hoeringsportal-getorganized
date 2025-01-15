<?php

namespace App\Repository;

use App\Entity\ExceptionLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ExceptionLogEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExceptionLogEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExceptionLogEntry[]    findAll()
 * @method ExceptionLogEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<ExceptionLogEntry>
 */
class ExceptionLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExceptionLogEntry::class);
    }
}
