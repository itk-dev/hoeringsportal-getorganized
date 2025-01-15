<?php

namespace App\Repository\GetOrganized;

use App\Entity\Archiver;
use App\Entity\GetOrganized\Document;
use App\GetOrganized\Document as GetOrganizedDocument;
use App\ShareFile\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Document|null find($id, $lockMode = null, $lockVersion = null)
 * @method Document|null findOneBy(array $criteria, array $orderBy = null)
 * @method Document[]    findAll()
 * @method Document[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findOneByItemAndArchiver(Item $item, Archiver $archiver): ?Document
    {
        return $this->findOneBy([
            'shareFileItemStreamId' => $item->streamId,
            'archiver' => $archiver,
        ]);
    }

    public function findOneByDocumentAndArchiver(GetOrganizedDocument $document, Archiver $archiver): ?Document
    {
        return $this->findOneBy([
            'docId' => $document->docId,
            'archiver' => $archiver,
        ]);
    }
}
