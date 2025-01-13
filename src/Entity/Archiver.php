<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Loggable;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * @Gedmo\Loggable()
 */
#[ORM\Entity(repositoryClass: \App\Repository\ArchiverRepository::class)]
#[UniqueEntity('name')]
class Archiver implements Loggable, \JsonSerializable, \Stringable
{
    use TimestampableEntity;

    public const TYPE_SHAREFILE2GETORGANIZED = 'sharefile2getorganized';
    public const TYPE_PDF_COMBINE = 'pdfcombine';
    public const TYPE_HEARING_OVERVIEW = 'hearing overview';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private $id;

    /**
     * @Gedmo\Versioned()
     */
    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    /**
     * @Gedmo\Versioned()
     */
    #[ORM\Column(type: 'text')]
    private $configuration;

    /**
     * @Gedmo\Versioned()
     */
    #[ORM\Column(type: 'boolean')]
    private $enabled;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $lastRunAt;

    #[ORM\Column(type: 'string', length: 255)]
    private $type;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getConfiguration(): ?string
    {
        return $this->configuration;
    }

    public function setConfiguration(string $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastRunAt(): ?\DateTimeInterface
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeInterface $lastRunAt): self
    {
        $this->lastRunAt = $lastRunAt;

        return $this;
    }

    public function getConfigurationValue(?string $key = null, $default = null)
    {
        $configuration = Yaml::parse($this->getConfiguration());

        if (null === $key) {
            return $configuration;
        }

        if (\array_key_exists($key, $configuration)) {
            return $configuration[$key];
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        return $propertyAccessor->getValue($configuration, $key) ?? $default;
    }

    public function getCreateCaseFile(): bool
    {
        $value = $this->getConfigurationValue('getorganized');

        return isset($value['project_id']);
    }

    public function getCreateHearing(): bool
    {
        return $this->getCreateCaseFile();
    }

    /**
     * Get GetOrganized organization reference (id) from Deskpro department id.
     *
     * @return int|null
     */
    public function getGetOrganizedOrganizationReference(?string $id)
    {
        $map = $this->getConfigurationValue('[getorganized][organizations]') ?? [];

        return $map[$id] ?? $map['default'] ?? null;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'configuration' => $this->getConfigurationValue(),
        ];
    }
}
