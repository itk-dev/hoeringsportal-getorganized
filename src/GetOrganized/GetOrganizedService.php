<?php

namespace App\GetOrganized;

use App\Entity\Archiver;
use App\Entity\GetOrganized\Document;
use App\GetOrganized\CaseEntity as GetOrganizedCase;
use App\GetOrganized\Document as GetOrganizedDocument;
use App\ShareFile\Item;
use App\Util\DocumentHelper;
use App\Util\TemplateHelper;
use ItkDev\GetOrganized\Client;
use ItkDev\GetOrganized\Service\Cases;
use ItkDev\GetOrganized\Service\Documents;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\TraceableHttpClient;

class GetOrganizedService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const CREATED = 'created';
    public const UPDATED = 'updated';

    private DocumentHelper $documentHelper;

    private Filesystem $filesystem;

    private Archiver $archiver;

    private array $configuration;

    private Client $client;
    private Cases $getOrganizedCases;
    private Documents $getOrganizedDocuments;

    private TemplateHelper $templateHelper;

    public function __construct(DocumentHelper $documentHelper, Filesystem $filesystem, TemplateHelper $templateHelper)
    {
        $this->documentHelper = $documentHelper;
        $this->filesystem = $filesystem;
        $this->templateHelper = $templateHelper;
        $this->setLogger(new NullLogger());
    }

    public function setArchiver(Archiver $archiver): self
    {
        $this->archiver = $archiver;
        $this->configuration = $archiver->getConfigurationValue('getorganized', []);
        $this->validateConfiguration();

        return $this;
    }

    public function getHearings()
    {
        return $this->getCases();
    }

    /**
     * Link a GetOrganized document and ShareFile item.
     */
    public function linkDocument(GetOrganizedCase $case, GetOrganizedDocument $document, Item $item, array $metadata, Archiver $archiver): Document
    {
        return $this->documentHelper->created($case, $document, $item, $metadata, $archiver);
    }

    public function createDocument(string $contents, CaseEntity $case, Item $item, array $metadata, array $options = []): Document
    {
        $path = $this->writeFile($contents, $item);
        $metadata = $this->buildMetadata($metadata, $options['item_metadata'] ?? []);

        $this->logger->debug(sprintf('%s; add to document library; %s', __METHOD__, json_encode(['case' => ['id' => $case->id], 'item' => ['id' => $item->id]])));
        $response = $this->getOrganizedDocuments()->AddToCaseSOAP(
            $path,
            $case->id,
            $item->name,
            $metadata
        );
        $this->logger->debug(sprintf('%s; add to document library; response: %s', __METHOD__, json_encode($response)));

        $this->finalizeDocument($response);

        return $this->documentHelper->created($case, new GetOrganizedDocument($response), $item, $metadata, $this->archiver);
    }

    public function updateDocument(Document $document, string $contents, CaseEntity $case, Item $item, array $metadata, array $options = []): Document
    {
        $path = $this->writeFile($contents, $item);
        $metadata = $this->buildMetadata($metadata, $options['item_metadata'] ?? []);

        $this->logger->debug(sprintf('%s; unfinalize document %d', __METHOD__, $document->getDocId()));
        // Un-finalize document to be able to update file.
        $response = $this->getOrganizedDocuments()->UnmarkFinalized([(int) $document->getDocId()]);
        $this->logger->debug(sprintf('%s; unfinalize document %d; response: %s', __METHOD__, $document->getDocId(), json_encode($response)));

        $this->logger->debug(sprintf('%s; add to document library; %s', __METHOD__, json_encode(['case' => ['id' => $case->id], 'item' => ['id' => $item->id]])));
        $response = $this->getOrganizedDocuments()->AddToDocumentLibrary(
            $path,
            $case->id,
            $item->name,
            $metadata,
            true
        );
        $this->logger->debug(sprintf('%s; add to document library; response: %s', __METHOD__, json_encode($response)));

        $this->finalizeDocument($response);

        return $this->documentHelper->updated($case, new GetOrganizedDocument($response), $item, $metadata, $this->archiver);
    }

    private function finalizeDocument(array $response): ?array
    {
        if (isset($response['DocId'])) {
            $this->logger->debug(sprintf('%s; finalize document; %s', __METHOD__, json_encode($response)));
            // Mark the document as finalized (“journaliseret”).
            $response = $this->getOrganizedDocuments()->Finalize((int) $response['DocId']);
            $this->logger->debug(sprintf('%s; finalize document; response: %s', __METHOD__, json_encode($response)));

            return $response;
        } else {
            $this->logger->error(sprintf('%s; finalize document; unexpected response: %s', __METHOD__, json_encode($response)));

            return null;
        }
    }

    private function writeFile(string $content, Item $item): string
    {
        $path = $this->filesystem->tempnam('/tmp', $item->id);
        $this->filesystem->dumpFile($path, $content);

        return $path;
    }

    private function buildMetadata(array $metadata, array $itemMetadata): array
    {
        if (isset($this->configuration['document']['metadata'])) {
            $metadata += $this->configuration['document']['metadata'];
        }

        // Process TWIG templates in metadata.
        $metadata = array_map(function ($value) use ($itemMetadata) {
            return false !== strpos($value, '{{') ? $this->templateHelper->render($value, ['item' => $itemMetadata]) : $value;
        }, $metadata);

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

    // Temporary cache.
    private $caseDocuments = [];

    public function getDocumentsByCaseId(string $caseId): ?array
    {
        if (!isset($this->caseDocuments[$caseId])) {
            $this->caseDocuments[$caseId] = $this->getOrganizedDocuments()->getDocumentsByCaseId($caseId);
        }

        return $this->caseDocuments[$caseId] ?? null;
    }

    public function getDocumentByFilename(string $caseId, string $filename): ?GetOrganizedDocument
    {
        $documents = $this->getDocumentsByCaseId($caseId);

        if (null !== $documents) {
            foreach ($this->caseDocuments[$caseId] as $document) {
                if (isset($document['ListItemAllFields']['DocID'])) {
                    $editUrl = $document['odata.editLink'] ?? null;
                    // Extract filename from edit url, e.g. "Web/GetFileByServerRelativePath(decodedurl='/cases/GEO277/GEO-2021-019143/Dokumenter/HS2672873-internal.pdf')")
                    if (preg_match('@decodedurl=[\'"][^\'"]*/(?P<filename>[^/]+)[\'"]@i', $editUrl, $matches)) {
                        if ($filename === $matches['filename']) {
                            return new GetOrganizedDocument(
                                $document['ListItemAllFields'] + [
                                    // Note change in case: DocID => DocId.
                                    'DocId' => $document['ListItemAllFields']['DocID'],
                                    'filename' => $filename,
                                ]
                            );
                        }
                    }
                }
            }
        }

        return null;
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
            $httpClient = new TraceableHttpClient(Client::createHttpClient(
                $this->configuration['api_username'],
                $this->configuration['api_password'],
                $this->configuration['api_url']
            ));
            $httpClient->setLogger($this->logger);

            $this->client = new Client(
                $this->configuration['api_username'],
                $this->configuration['api_password'],
                $this->configuration['api_url'],
                $httpClient
            );
        }

        return $this->client;
    }
}
