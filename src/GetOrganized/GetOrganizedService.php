<?php

namespace App\GetOrganized;

use App\Entity\Archiver;
use App\Repository\GetOrganized\DocumentRepository;
use App\ShareFile\Item;
use App\Util\TemplateHelper;
use ItkDev\GetOrganized\Client;
use ItkDev\GetOrganized\Service\Cases;
use ItkDev\GetOrganized\Service\Documents;

class GetOrganizedService
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';

    private DocumentRepository $documentRepository;

    private TemplateHelper $template;

    private Archiver $archiver;

    private array $configuration;

    private Client $client;
    private Cases $getOrganizedCases;
    private Documents $getOrganizedDocuments;

    public function __construct(DocumentRepository $documentRepository, TemplateHelper $template)
    {
        $this->documentRepository = $documentRepository;
        $this->template = $template;
    }

    public function setArchiver(Archiver $archiver)
    {
        $this->archiver = $archiver;
        $this->configuration = $archiver->getConfigurationValue('getorganized', []);
        $this->validateConfiguration();
    }

    public function updateDocumentSettings(Document $document, array $data)
    {
        $return = $this->getOrganizedCases()->updateDocument($document, $data);
    }

    public function getHearings()
    {
        return $this->getCases();
    }

    public function createDocument(string $contents, CaseEntity $case, Item $item, array $metadata): \App\Entity\GetOrganized\Document
    {
        $path = $this->writeFile($contents, $item);
        $metadata = $this->buildMetadata($metadata);

        $response = $this->getOrganizedDocuments()->AddToDocumentLibrary(
            $path,
            $case->id,
            basename($path),
            $metadata
        );

        return $this->documentRepository->created(new Document($response), $item, $metadata, $this->archiver);
    }

    public function updateDocument(string $contents, CaseEntity $case, Item $item, array $metadata): \App\Entity\GetOrganized\Document
    {
        $path = $this->writeFile($contents, $item);
        $metadata = $this->buildMetadata($metadata);

        $response = $this->getOrganizedDocuments()->AddToDocumentLibrary(
            $path,
            $case->id,
            basename($path),
            $metadata,
            true
        );

        return $this->documentRepository->updated(new Document($response), $item, $metadata, $this->archiver);
    }

    private function writeFile(string $contents, Item $item): string
    {
        $name = $this->getDocumentName($item);
        $path = tempnam('/tmp', $name);
        file_put_contents($path, $contents);

        return $path;
    }

    private function buildMetadata(array $metadata): array
    {
        if (isset($this->configuration['document']['metadata'])) {
            $metadata += $this->configuration['document']['metadata'];
        }

        return $metadata;
    }

    /**
     * @return array|CaseEntity[]
     */
    public function getCases(array $criteria = [])
    {
        $result = $this->getOrganizedCases()->FindCases($criteria);

        return array_map(static function (array $data) {
            return new CaseEntity($data);
        }, $result);
    }

    public function getCaseById(string $id): ?CaseEntity
    {
        $result = $this->getCases([
            'CaseIdFilter' => $id,
            'IncludeRegularCases' => true,
            'ItemCount' => 1,
        ]);

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getCaseByName(string $name)
    {
        $result = $this->getCases(['TitleText' => $name]);

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getDocumentsBy(array $criteria)
    {
        return $this->getOrganizedCases()->searchDocument($criteria);
    }

    public function getDocumentById(string $id)
    {
        $result = $this->getOrganizedCases()->searchDocument(['DocumentIdentifier' => $id]);

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getDocumentByNumber(string $number)
    {
        $result = $this->getOrganizedCases()->searchDocument(['DocumentNumber' => $number]);

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getDocumentVersion(string $documentVersionIdentifier)
    {
        return $this->getOrganizedCases()->getDocumentVersion($documentVersionIdentifier);
    }

    private function getDocumentName(Item $item)
    {
        $template = $this->configuration['document']['name'] ?? '{{ item.name }}';

        return $this->template->render($template, ['item' => ['name' => $item->name] + ($item->metadata ?? [])]);
    }

    private function validateConfiguration()
    {
        // @HACK
        if (empty($this->configuration)) {
            return;
        }

        $requiredFields = ['api_url', 'api_username', 'api_password'];

        foreach ($requiredFields as $field) {
            if (!isset($this->configuration[$field])) {
                throw new \RuntimeException(sprintf('Configuration value %s missing or empty.', $field));
            }
        }
    }

    private function getOrganizedCases()
    {
        if (empty($this->getOrganizedCases)) {
            $this->getOrganizedCases = $this->client()->api('cases');
        }

        return $this->getOrganizedCases;
    }

    private function getOrganizedDocuments()
    {
        if (empty($this->getOrganizedDocuments)) {
            $this->getOrganizedDocuments = $this->client()->api('documents');
        }

        return $this->getOrganizedDocuments;
    }

    private function client()
    {
        if (empty($this->client)) {
            $this->client = new Client(
                $this->configuration['api_username'],
                $this->configuration['api_password'],
                $this->configuration['api_url']
            );
        }

        return $this->client;
    }
}
