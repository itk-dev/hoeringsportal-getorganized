<?php

namespace App\ShareFile;

use App\Entity\Archiver;
use Kapersoft\ShareFile\Client;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ShareFileService implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    private const string SHAREFILE_FOLDER = 'ShareFile.Api.Models.Folder';
    private const string SHAREFILE_FILE = 'ShareFile.Api.Models.File';

    private array $configuration;

    private Client $client;

    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    public function setArchiver(Archiver $archiver): self
    {
        $this->configuration = $archiver->getConfigurationValue('sharefile', []);
        $this->validateConfiguration();

        return $this;
    }

    /**
     * Check that we can connect to ShareFile.
     */
    public function connect(): void
    {
        $this->client()->getItemById($this->configuration['root_id']);
    }

    /**
     * @return Item[]
     */
    public function getUpdatedFiles(\DateTimeInterface $changedAfter): array
    {
        $hearings = $this->getHearings($changedAfter);
        foreach ($hearings as &$hearing) {
            $responses = $this->getResponses($hearing, $changedAfter);
            foreach ($responses as &$response) {
                $files = $this->getFiles($response, $changedAfter);
                $response->setChildren($files);
            }
            $hearing->setChildren($responses);
        }

        return $hearings;
    }

    /**
     * @return Item[]
     */
    public function getUpdatedOverviewFiles(\DateTimeInterface $changedAfter): array
    {
        $hearings = $this->getHearings($changedAfter);
        foreach ($hearings as &$hearing) {
            $files = $this->getFiles($hearing, $changedAfter);
            $hearing->setChildren($files);
        }

        return $hearings;
    }

    public function getHearingOverviewFiles(string $hearingItemId): Item
    {
        $hearing = $this->getHearing($hearingItemId);
        $files = $this->getFiles($hearing);
        $hearing->setChildren($files);

        return $hearing;
    }

    /**
     * @return Item[]
     */
    public function getHearings(?\DateTimeInterface $changedAfter = null): array
    {
        $itemId = $this->configuration['root_id'];
        $folders = $this->getFolders($itemId, $changedAfter);
        $hearings = array_filter($folders, function ($item) use ($changedAfter) {
            if ($changedAfter && isset($item['ProgenyEditDate'])
                && new \DateTime($item['ProgenyEditDate']) < $changedAfter) {
                return false;
            }

            return $this->isHearing($item);
        });

        return $this->construct(Item::class, $hearings);
    }

    public function findHearing(string $name): Item
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $itemId = $this->configuration['root_id'];

        $result = $this->client()->getChildren(
            $itemId,
            [
                '$filter' => 'Name eq \''.str_replace('\'', '\\\'', $name).'\'',
            ]
        );

        if (!isset($result['value']) || 1 !== \count($result['value'])) {
            throw new \RuntimeException('Invalid hearing: '.$name);
        }

        return new Item(reset($result['value']));
    }

    public function getHearing(string $itemId): Item
    {
        $hearing = $this->getItem($itemId);
        $responses = $this->getResponses($hearing);
        foreach ($responses as &$response) {
            $files = $this->getFiles($response);
            $response->setChildren($files);
        }
        $hearing->setChildren($responses);

        return $hearing;
    }

    /**
     * @return Item[]
     */
    public function getResponses(Item $hearing, ?\DateTimeInterface $changedAfter = null): array
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $folders = $this->getFolders($hearing, $changedAfter);
        $responses = array_filter($folders, function ($item) use ($changedAfter) {
            if ($changedAfter && isset($item['ProgenyEditDate'])
                    && new \DateTime($item['ProgenyEditDate']) < $changedAfter) {
                return false;
            }

            return $this->isHearingResponse($item);
        });

        return $this->construct(Item::class, $responses);
    }

    public function getItem(string|Item $item): Item
    {
        $itemId = $this->getItemId($item);
        $item = $this->client()->getItemById($itemId);

        $this->setMetadata($item);

        return new Item($item);
    }

    /**
     * Get metadata list.
     */
    public function getMetadata(string|Item $item, ?array $names = null): array
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $itemId = $this->getItemId($item);
        $metadata = $this->client()->getItemMetadataList($itemId);

        if (null !== $names) {
            $metadata['value'] = array_filter($metadata['value'], fn ($item) => isset($item['Name']) && \in_array($item['Name'], $names, true));
        }

        $result = [];
        foreach ($metadata['value'] as $metadatum) {
            $result[$metadatum['Name']] = $metadatum;
        }

        return $result;
    }

    /**
     * Get all metadata values.
     */
    public function getMetadataValues(string|Item $item, ?array $names = null): array
    {
        $metadata = $this->getMetadata($item, $names);

        return array_map(function ($metadatum) {
            $value = $metadatum['Value'];

            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception) {
                return $value;
            }
        }, $metadata);
    }

    /**
     * Get a single metadata value.
     */
    public function getMetadataValue(string|Item $item, string $name): mixed
    {
        $metadata = $this->getMetadataValues($item, [$name]);

        return $metadata[$name] ?? null;
    }

    public function getFiles(string|Item $item, ?\DateTimeInterface $changedAfter = null): array
    {
        $itemId = $this->getItemId($item);
        $children = $this->getChildren($itemId, self::SHAREFILE_FILE, $changedAfter);
        $files = array_filter($children, fn ($item) => !(null !== $changedAfter && isset($item['CreationDate'])
            && new \DateTime($item['CreationDate']) < $changedAfter));

        return $this->construct(Item::class, $files);
    }

    public function getFolders(string|Item $item, ?\DateTimeInterface $changedAfter = null): array
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $itemId = $this->getItemId($item);

        $folders = $this->getChildren($itemId, self::SHAREFILE_FOLDER, $changedAfter);

        // Add metadata values to each folder.
        foreach ($folders as &$folder) {
            $this->setMetadata($folder);
        }

        return $folders;
    }

    public function downloadFile(string|Item $item): string
    {
        $itemId = $this->getItemId($item);

        return $this->client()->getItemContents($itemId);
    }

    public function uploadFile(string $filename, string $folderId, bool $unzip = false, bool $overwrite = true, bool $notify = true): string
    {
        $result = $this->client()->uploadFileStandard($filename, $folderId, $unzip, $overwrite, $notify);

        return $result;
    }

    public function findFile(string $filename, string $folderId): Item
    {
        $result = $this->client()->getChildren(
            $folderId,
            [
                '$filter' => 'Name eq \''.str_replace('\'', '\\\'', $filename).'\'',
            ]
        );

        if (!isset($result['value']) || 1 !== \count($result['value'])) {
            throw new \RuntimeException(sprintf('No such file %s in folder %s', $filename, $folderId));
        }

        return new Item(reset($result['value']));
    }

    /**
     * @param Item[] $hearings
     */
    public function dump(array $hearings, OutputInterface $output): void
    {
        $table = new Table($output);

        foreach ($hearings as $hearing) {
            $table->addRow([
                $hearing->name,
                $hearing->id,
                $hearing->progenyEditDate,
            ]);
            foreach ($hearing->getChildren() as $reply) {
                $table->addRow([
                    ' '.$reply->name,
                    $reply->id,
                    $reply->progenyEditDate,
                    json_encode($this->getMetadata($reply), JSON_PRETTY_PRINT),
                ]);
                foreach ($reply->getChildren() as $file) {
                    $table->addRow([
                        '  '.$file->name,
                        $file->id,
                    ]);
                }
            }
        }

        $table->render();
    }

    private function setMetadata(array &$item): void
    {
        $item['_metadata'] = $this->getMetadataValues($item['Id'], ['agent_data', 'ticket_data', 'user_data']);
    }

    private function validateConfiguration(): void
    {
        $requiredFields = ['hostname', 'client_id', 'secret', 'username', 'password', 'root_id'];
        foreach ($requiredFields as $field) {
            if (!isset($this->configuration[$field])) {
                throw new \RuntimeException('Configuration value "'.$field.'" missing.');
            }
        }
    }

    private function getItemId(string|Item $item): string
    {
        return $item instanceof Item ? $item->id : $item;
    }

    private function getChildren(string $itemId, string $type, ?\DateTimeInterface $changedAfter = null): array
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $query = [
            '$filter' => 'isof(\''.$type.'\')',
        ];

        return $this->getAllChildren($itemId, $query);
    }

    /**
     * Get all children by following "nextlink" in result.
     */
    private function getAllChildren(string $itemId, array $query): array
    {
        $this->debug(sprintf('%s(%s)', __METHOD__, json_encode(func_get_args())));

        $result = $this->client()->getChildren($itemId, $query);

        if (!isset($result['value'])) {
            return [];
        }

        $values[] = $result['value'];

        $pageSize = \count($result['value']);
        if ($pageSize > 0) {
            $numberOfPages = (int) ceil($result['odata.count'] / $pageSize);
            for ($page = 2; $page <= $numberOfPages; ++$page) {
                $query['$skip'] = $pageSize * ($page - 1);
                $result = $this->client()->getChildren($itemId, $query);
                if (isset($result['value'])) {
                    $values[] = $result['value'];
                }
            }
        }

        // Flatten the results.
        return array_merge(...$values);
    }

    private function client(): Client
    {
        if (empty($this->client)) {
            $this->client = new ShareFileClient(
                $this->configuration['hostname'],
                $this->configuration['client_id'],
                $this->configuration['secret'],
                $this->configuration['username'],
                $this->configuration['password']
            );
        }

        return $this->client;
    }

    private function isHearing(array $item): bool
    {
        return (bool) preg_match('/^H([a-z-]+)?[0-9]+$/i', (string) $item['Name']);
    }

    private function isHearingResponse(array $item): bool
    {
        return (bool) preg_match('/^HS[0-9]+$/', (string) $item['Name']);
    }

    private function construct(string $class, array $items): array
    {
        return array_map(fn (array $data) => new $class($data), $items);
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
