<?php

namespace App\ShareFile;

use App\Entity\Archiver;
use Kapersoft\ShareFile\Client;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ShareFileService
{
    private const SHAREFILE_FOLDER = 'ShareFile.Api.Models.Folder';
    private const SHAREFILE_FILE = 'ShareFile.Api.Models.File';

    private array $configuration;

    private Client $client;

    public function setArchiver(Archiver $archiver)
    {
        $this->configuration = $archiver->getConfigurationValue('sharefile', []);
        $this->validateConfiguration();
    }

    /**
     * Check that we can connect to ShareFile.
     */
    public function connect()
    {
        $this->client()->getItemById($this->configuration['root_id']);
    }

    /**
     * @return Item[]
     */
    public function getUpdatedFiles(\DateTimeInterface $changedAfter)
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
    public function getUpdatedOverviewFiles(\DateTimeInterface $changedAfter)
    {
        $hearings = $this->getHearings($changedAfter);
        foreach ($hearings as &$hearing) {
            $files = $this->getFiles($hearing, $changedAfter);
            $hearing->setChildren($files);
        }

        return $hearings;
    }

    /**
     * @param mixed $hearingItemId
     *
     * @return Item[]
     */
    public function getHearingOverviewFiles($hearingItemId)
    {
        $hearing = $this->getHearing($hearingItemId);
        if (null !== $hearing) {
            $files = $this->getFiles($hearing);
            $hearing->setChildren($files);
        }

        return $hearing;
    }

    /**
     * @return Item[]
     */
    public function getHearings(\DateTimeInterface $changedAfter = null)
    {
        $itemId = $this->configuration['root_id'];
        $folders = $this->getFolders($itemId, $changedAfter);
        $hearings = array_filter($folders ?? [], function ($item) use ($changedAfter) {
            if ($changedAfter && isset($item['ProgenyEditDate'])
                && new \DateTime($item['ProgenyEditDate']) < $changedAfter) {
                return false;
            }

            return $this->isHearing($item);
        });

        return $this->construct(Item::class, $hearings);
    }

    public function findHearing($name)
    {
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

    public function getHearing($itemId)
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
    public function getResponses(Item $hearing, \DateTimeInterface $changedAfter = null)
    {
        $folders = $this->getFolders($hearing, $changedAfter);
        $responses = array_filter($folders ?? [], function ($item) use ($changedAfter) {
            if ($changedAfter && isset($item['ProgenyEditDate'])
                    && new \DateTime($item['ProgenyEditDate']) < $changedAfter) {
                return false;
            }

            return $this->isHearingResponse($item);
        });

        return $this->construct(Item::class, $responses);
    }

    /**
     * @param string|Item $item
     *
     * @return Item
     */
    public function getItem($item)
    {
        $itemId = $this->getItemId($item);
        $item = $this->client()->getItemById($itemId);

        $this->setMetadata($item);

        return new Item($item);
    }

    /**
     * Get metadata list.
     *
     * @param string|Item $item
     *
     * @return array
     */
    public function getMetadata($item, array $names = null)
    {
        $itemId = $this->getItemId($item);
        $metadata = $this->client()->getItemMetadataList($itemId);

        if (null !== $names) {
            $metadata['value'] = array_filter($metadata['value'], function ($item) use ($names) {
                return isset($item['Name']) && \in_array($item['Name'], $names, true);
            });
        }

        $result = [];
        foreach ($metadata['value'] as $metadatum) {
            $result[$metadatum['Name']] = $metadatum;
        }

        return $result;
    }

    /**
     * Get all metadata values.
     *
     * @param string|Item $item
     *
     * @return array
     */
    public function getMetadataValues($item, array $names = null)
    {
        $metadata = $this->getMetadata($item, $names);

        return array_map(function ($metadatum) {
            $value = $metadatum['Value'];

            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                return $value;
            }
        }, $metadata);
    }

    /**
     * Get a single metadata value.
     *
     * @param string|Item $item
     *
     * @return mixed|null
     */
    public function getMetadataValue($item, string $name)
    {
        $metadata = $this->getMetadataValues($item, [$name]);

        return $metadata[$name] ?? null;
    }

    public function getFiles($item, \DateTimeInterface $changedAfter = null)
    {
        $itemId = $this->getItemId($item);
        $children = $this->getChildren($itemId, self::SHAREFILE_FILE, $changedAfter);
        $files = array_filter($children ?? [], function ($item) use ($changedAfter) {
            return !(null !== $changedAfter && isset($item['CreationDate'])
                && new \DateTime($item['CreationDate']) < $changedAfter);
        });

        return $this->construct(Item::class, $files);
    }

    public function getFolders($item, \DateTimeInterface $changedAfter = null)
    {
        $itemId = $this->getItemId($item);

        $folders = $this->getChildren($itemId, self::SHAREFILE_FOLDER, $changedAfter);

        // Add metadata values to each folder.
        foreach ($folders as &$folder) {
            $this->setMetadata($folder);
        }

        return $folders;
    }

    public function downloadFile($item)
    {
        $itemId = $this->getItemId($item);

        return $this->client()->getItemContents($itemId);
    }

    public function uploadFile(string $filename, string $folderId, bool $unzip = false, bool $overwrite = true, bool $notify = true)
    {
        $result = $this->client()->uploadFileStandard($filename, $folderId, $unzip, $overwrite, $notify);

        return $result;
    }

    public function findFile(string $filename, string $folderId)
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
    public function dump(array $hearings, OutputInterface $output)
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

    private function setMetadata(array &$item)
    {
        $item['_metadata'] = $this->getMetadataValues($item['Id'], ['agent_data', 'ticket_data', 'user_data']);
    }

    private function validateConfiguration()
    {
        $requiredFields = ['hostname', 'client_id', 'secret', 'username', 'password', 'root_id'];
        foreach ($requiredFields as $field) {
            if (!isset($this->configuration[$field])) {
                throw new \RuntimeException('Configuration value "'.$field.'" missing.');
            }
        }
    }

    /**
     * @param string|Item $item
     *
     * @return string
     */
    private function getItemId($item)
    {
        return $item instanceof Item ? $item->id : $item;
    }

    private function getChildren(string $itemId, string $type, \DateTimeInterface $changedAfter = null)
    {
        $query = [
            '$filter' => 'isof(\''.$type.'\')',
        ];

        return $this->getAllChildren($itemId, $query);
    }

    /**
     * Get all children by following "nextlink" in result.
     *
     * @return array
     */
    private function getAllChildren(string $itemId, array $query)
    {
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

    /**
     * @throws \Exception
     *
     * @return Client
     */
    private function client()
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

    private function isHearing(array $item)
    {
        return preg_match('/^H([a-z-]+)?[0-9]+$/i', $item['Name']);
    }

    private function isHearingResponse(array $item)
    {
        return preg_match('/^HS[0-9]+$/', $item['Name']);
    }

    private function construct($class, array $items)
    {
        return array_map(function (array $data) use ($class) {
            return new $class($data);
        }, $items);
    }
}
