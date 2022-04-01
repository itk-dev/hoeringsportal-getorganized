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
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findOneByItemAndArchiver(Item $item, Archiver $archiver)
    {
        return $this->findOneBy([
            'shareFileItemStreamId' => $item->streamId,
            'archiver' => $archiver,
        ]);
    }

    public function findOneByDocumentAndArchiver(GetOrganizedDocument $document, Archiver $archiver)
    {
        return $this->findOneBy([
            'docId' => $document->docId,
            'archiver' => $archiver,
        ]);
    }

    public function created(GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver): Document
    {
        $docId = $document->docId;
        $shareFileItemStreamId = $item->streamId;

        $entity = $this->findOneBy([
            'docId' => $docId,
            'shareFileItemStreamId' => $shareFileItemStreamId,
            'archiver' => $archiver,
        ]);

        if (null === $entity) {
            $entity = (new Document())
                ->setDocId($docId)
                ->setShareFileItemStreamId($shareFileItemStreamId)
                ->setArchiver($archiver);
        }

        $entity
            ->setShareFileItemId($item->id)
            ->setData([
                'sharefile' => $item->getData(),
                'getorganized' => $document->getData(),
                'metadata' => $metadata,
            ])
            ->setUpdatedAt(new \DateTime());

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        return $entity;
    }

    public function updated(GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver)
    {
        $this->created($document, $item, $metadata, $archiver);
    }
}
