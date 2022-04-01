<?php

namespace App\Entity\GetOrganized;

use App\Entity\Archiver;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Repository\GetOrganized\DocumentRepository")
 * @ORM\Table(name="get_organized_document")
 */
class Document
{
    use TimestampableEntity;

    /**
     * @ORM\Id()
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * The GetOrganized document id.
     *
     * @ORM\Column(type="string", length=255)
     */
    private $docId;

    /**
     * @ORM\Column(type="json")
     */
    private $data = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Archiver")
     * @ORM\JoinColumn(nullable=false)
     */
    private $archiver;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $shareFileItemId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $shareFileItemStreamId;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDocId(): ?string
    {
        return $this->docId;
    }

    public function setDocId(string $docId): self
    {
        $this->docId = $docId;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getArchiver(): ?Archiver
    {
        return $this->archiver;
    }

    public function setArchiver(?Archiver $archiver): self
    {
        $this->archiver = $archiver;

        return $this;
    }

    public function getShareFileItemId(): ?string
    {
        return $this->shareFileItemId;
    }

    public function setShareFileItemId(string $shareFileItemId): self
    {
        $this->shareFileItemId = $shareFileItemId;

        return $this;
    }

    public function getShareFileItemStreamId(): ?string
    {
        return $this->shareFileItemStreamId;
    }

    public function setShareFileItemStreamId(string $shareFileItemStreamId): self
    {
        $this->shareFileItemStreamId = $shareFileItemStreamId;

        return $this;
    }
}