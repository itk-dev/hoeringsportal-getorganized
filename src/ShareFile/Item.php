<?php

namespace App\ShareFile;

class Item extends Entity
{
    /**
     * Item id. Note this will change when a new version of the item is created.
     *
     * @see self::$streamId;
     *
     * @var string
     */
    public $id;

    /**
     * Unique id that will never change even when new versions of the item are
     * created.
     *
     * @see https://api.sharefile.com/rest/docs/resource.aspx?name=ShareFile.Api.Models.Item
     *
     * @var string
     */
    public $streamId;

    public $name;

    public $progenyEditDate;

    public $creationDate;

    public $metadata;

    public $fileName;

    /**
     * @var Item[]
     */
    protected $children;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setChildren(array $children)
    {
        $this->children = $children;

        return $this;
    }

    /**
     * @return Item[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    protected function build(array $data)
    {
        parent::build($data);
        $this->id = $data['Id'];
        $this->streamId = $data['StreamID'];
        $this->name = $data['Name'];
        $this->progenyEditDate = $data['ProgenyEditDate'] ?? null;
        $this->creationDate = $data['CreationDate'];
        $this->metadata = $data['_metadata'] ?? null;
        $this->fileName = $data['FileName'] ?? null;
    }
}
