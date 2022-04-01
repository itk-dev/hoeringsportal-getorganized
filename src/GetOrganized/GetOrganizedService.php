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

    public function getDocument(CaseFile $case, Item $item)
    {
        $document = $this->documentRepository->findOneByItemAndArchiver($item, $this->archiver);

        return $document ? $this->getDocumentById($document->getDocumentIdentifier()) : null;
    }

    public function updateDocumentSettings(Document $document, array $data)
    {
        $return = $this->getOrganizedCases()->updateDocument($document, $data);
    }

    public function getHearings()
    {
        return $this->getCases();
    }

    /**
     * Create a case file.
     *
     * @param array $data additional data for new case file
     *
     * @throws \ItkDev\Edoc\Util\EdocException
     *
     * @return CaseFile
     */
    public function createCase(Item $item, array $data = [], array $config = [])
    {
        $name = $this->getCaseName($item);
        $data += [
            'TitleText' => $name,
        ];

        if (isset($this->configuration['project_id'])) {
            $data += ['Project' => $this->configuration['project_id']];
        }

        if (isset($this->configuration['case_file']['defaults'])) {
            $data += $this->configuration['case_file']['defaults'];
        }

        $caseFile = $this->getOrganizedCases()->createCaseFile($data);

        $this->caseFileRepository->created($caseFile, $item, $this->archiver);

        if (\is_callable($config['callback'] ?? null)) {
            $config['callback']([
                'status' => self::CREATED,
                'item' => $item,
                'data' => $data,
                'case_file' => $caseFile,
            ]);
        }

        return $caseFile;
    }

    public function updateCaseFile(CaseFile $caseFile, Item $item, array $data)
    {
        if ($this->getOrganizedCases()->updateCaseFile($caseFile, $data)) {
            $this->caseFileRepository->updated($caseFile, $item, $this->archiver);

            return $this->getCaseById($caseFile->CaseFileIdentifier);
        }

        return null;
    }

    /**
     * Get a hearing reponse.
     *
     * @param string $item
     * @param bool   $create if true, a new response will be created
     * @param array  $data   additional data for new response
     *
     * @return Document
     */
    public function getResponse(CaseEntity $hearing, Item $item, bool $create = false, array $data = [])
    {
        $document = $this->documentRepository->findOneByItemAndArchiver($item, $this->archiver);

        $response = $document ? $this->getDocumentById($document->getDocumentIdentifier()) : null;
        if (null !== $response || !$create) {
            // @TODO Update response
            return $response;
        }

        return $this->createDocument($hearing, $item, $data);
    }

    /**
     * Ensure that a document exists in eDoc.
     *
     * @param bool $create
     *
     * @throws \ItkDev\Edoc\Util\EdocException
     *
     * @return Document|mixed|null
     */
    public function ensureDocument(CaseFile $hearing, Item $item, array $data = [])
    {
        $document = $this->documentRepository->findOneByItemAndArchiver($item, $this->archiver);

        $edocDocument = $document ? $this->getDocumentById($document->getDocumentIdentifier()) : null;
        if (null !== $edocDocument) {
            // @TODO Update document.
            return $edocDocument;
        }

        return $this->createDocument($hearing, $item, $data);
    }

    public function getDocumentUpdatedAt(Document $document)
    {
        $document = $this->documentRepository->findOneByDocumentAndArchiver($document, $this->archiver);

        return $document ? $document->getUpdatedAt() : null;
    }

    /**
     * Create a hearing response.
     *
     * @param Item  $item     the response name
     * @param array $metadata data for new response
     *
     * @return Document
     *
     *@throws \ItkDev\Edoc\Util\EdocException
     */
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
     * @return array|CaseFile[]
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

    public function getDocumentList(CaseFile $case)
    {
        return $this->getOrganizedCases()->getDocumentList($case);
    }

    public function getDocuments(CaseFile $case)
    {
        return $this->getOrganizedCases()->searchDocument([
            'CaseFileIdentifier' => '200031',
        ]);
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

    public function getDocumentByName(CaseFile $case, string $name)
    {
        $result = $this->getOrganizedCases()->searchDocument([
            'CaseFileReference' => $case->CaseFileIdentifier,
            'TitleText' => $name,
        ]);

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getDocumentVersion(string $documentVersionIdentifier)
    {
        return $this->getOrganizedCases()->getDocumentVersion($documentVersionIdentifier);
    }

    public function getCaseWorkerByAz($az)
    {
        $az = 'adm\\'.$az;
        $result = $this->getOrganizedCases()->getItemList(
            ItemListType::CASE_WORKER,
            [
                'CaseWorkerAccountName' => $az,
            ]
        );

        return 1 === \count($result) ? reset($result) : null;
    }

    public function getDocumentTypeByName(string $name)
    {
        if (null === $this->documentTypes) {
            $this->documentTypes = $this->getOrganizedCases()->getItemList(ItemListType::DOCUMENT_TYPE);
        }

        if (\is_array($this->documentTypes)) {
            foreach ($this->documentTypes as $item) {
                if (0 === strcasecmp($name, $item['DocumentTypeName'])) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function getDocumentStatusByName(string $name)
    {
        if (null === $this->documentStatuses) {
            $this->documentStatuses = $this->getOrganizedCases()->getItemList(ItemListType::DOCUMENT_STATUS_CODE);
        }

        if (\is_array($this->documentStatuses)) {
            foreach ($this->documentStatuses as $item) {
                if (0 === strcasecmp($name, $item['DocumentStatusCodeName'])) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function getCaseName(Item $item)
    {
        $template = $this->configuration['case']['name'] ?? '{{ item.name }}';

        return $this->template->render($template, ['item' => ['name' => $item->name] + $item->metadata]);
    }

    private function getDocumentName(Item $item)
    {
        $template = $this->configuration['document']['name'] ?? '{{ item.name }}';

        return $this->template->render($template, ['item' => ['name' => $item->name] + ($item->metadata ?? [])]);
    }

    private function validateConfiguration()
    {
        // @HACK
        if (null === $this->configuration) {
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
