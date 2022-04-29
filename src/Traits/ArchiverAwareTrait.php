<?php

namespace App\Traits;

use App\Entity\Archiver;

trait ArchiverAwareTrait
{
    private Archiver $archiver;

    public function setArchiver(Archiver $archiver)
    {
        $this->archiver = $archiver;

        $archiverType = static::$archiverType ?? null;
        if (null === $archiverType) {
            throw new \RuntimeException(sprintf('Archiver type not set in %s', static::class));
        }

        if ($archiver->getType() !== $archiverType) {
            throw new \RuntimeException(sprintf('Cannot handle archiver with type %s; %s expected', $archiver->getType(), $archiverType));
        }

        if (!$archiver->isEnabled()) {
            throw new \RuntimeException(sprintf('Archiver %s is not enabled.', $archiver));
        }
    }

    public function getArchiver(): ?Archiver
    {
        return $this->archiver;
    }
}
