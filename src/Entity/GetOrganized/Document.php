<?php

namespace App\Entity\GetOrganized;

use App\Entity\Archiver;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: \App\Repository\GetOrganized\DocumentRepository::class)]
#[ORM\Table(name: 'get_organized_document')]
class Document
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /**
     * The GetOrganized case id.
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $caseId;

    /**
     * The GetOrganized document id.
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $docId;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\ManyToOne(targetEntity: Archiver::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Archiver $archiver;

    #[ORM\Column(type: 'string', length: 255)]
    private string $shareFileItemId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $shareFileItemStreamId;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCaseId(): ?string
    {
        return $this->caseId;
    }

    public function setCaseId(string $caseId): self
    {
        $this->caseId = $caseId;

        return $this;
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

    public function setArchiver(Archiver $archiver): self
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

    public function getFileInfo(): ?string
    {
        $formatBytes = function ($size, $precision = 2) {
            $base = log($size, 1024);
            $suffixes = ['', 'K', 'M', 'G', 'T'];

            return trim(round(1024 ** ($base - floor($base)), $precision).' '.$suffixes[floor($base)]);
        };

        return sprintf(
            '%s (%s)',
            $this->data['sharefile']['FileName'] ?? null,
            $formatBytes($this->data['sharefile']['FileSizeBytes'] ?? 0)
        );
    }
}
