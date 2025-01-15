<?php

namespace App\Entity;

use App\Repository\ExceptionLogEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Yaml;

#[ORM\Entity(repositoryClass: ExceptionLogEntryRepository::class)]
class ExceptionLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::JSON)]
    private ?array $data = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $hidden = false;

    public function __construct(\Throwable $t, array $context = [])
    {
        $trace = array_map(function (array $frame) {
            $frame['num_args'] = \count($frame['args'] ?? []);
            unset($frame['args']);

            return $frame;
        }, $t->getTrace());

        $this->createdAt = new \DateTime();
        $this->message = mb_substr($t->getMessage(), 0, 255);
        $this->data = [
            'message' => $t->getMessage(),
            'trace' => $trace,
            'context' => $context,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getTrace(): ?array
    {
        return $this->data['trace'] ?? null;
    }

    public function getTraceYaml(): ?string
    {
        return Yaml::dump($this->getTrace());
    }

    public function getContext(): ?array
    {
        return $this->data['context'] ?? null;
    }

    public function getContextYaml(): ?string
    {
        return Yaml::dump($this->getContext());
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }
}
