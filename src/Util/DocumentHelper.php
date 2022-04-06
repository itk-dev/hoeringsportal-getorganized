<?php

namespace App\Util;

use App\Entity\Archiver;
use App\Entity\GetOrganized\Document;
use App\GetOrganized\CaseEntity as GetOrganizedCase;
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

    public function created(GetOrganizedCase $case, GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver): Document
    {
        $caseId = $case->id;
        $docId = $document->docId;
        $shareFileItemStreamId = $item->streamId;

        $entity = $this->documentRepository->findOneBy([
            'docId' => $docId,
            'shareFileItemStreamId' => $shareFileItemStreamId,
            'archiver' => $archiver,
        ]);

        if (null === $entity) {
            $entity = (new Document())
                ->setCaseId($caseId)
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

    public function updated(GetOrganizedCase $case, GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver)
    {
        return $this->created($case, $document, $item, $metadata, $archiver);
    }
}
