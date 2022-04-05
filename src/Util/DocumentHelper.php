<?php

namespace App\Util;

use App\Entity\Archiver;
use App\Entity\GetOrganized\Document;
use App\GetOrganized\Document as GetOrganizedDocument;
use App\Repository\GetOrganized\DocumentRepository;
use App\ShareFile\Item;
use Doctrine\ORM\EntityManagerInterface;

class DocumentHelper
{
    private DocumentRepository $documentRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(DocumentRepository $documentRepository, EntityManagerInterface $entityManager)
    {
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
    }

    public function created(GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver): Document
    {
        $docId = $document->docId;
        $shareFileItemStreamId = $item->streamId;

        $entity = $this->documentRepository->findOneBy([
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

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    public function updated(GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver)
    {
        return $this->created($document, $item, $metadata, $archiver);
    }
}
