<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Yaml;

#[ORM\Entity(repositoryClass: \App\Repository\ExceptionLogEntryRepository::class)]
class ExceptionLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    // @phpstan-ignore-next-line
    private ?int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $message;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'json')]
    private array $data;

    #[ORM\Column(type: 'boolean')]
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
