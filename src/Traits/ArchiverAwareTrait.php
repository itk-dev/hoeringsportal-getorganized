<?php

namespace App\Traits;

use App\Entity\Archiver;

trait ArchiverAwareTrait
{
    private Archiver $archiver;

    private function setArchiver(Archiver $archiver)
    {
        $this->archiver = $archiver;

        if (!isset($this->archiverType)) {
            throw new \RuntimeException(sprintf('Archiver type not set in %s', self::class));
        }

        if ($archiver->getType() !== $this->archiverType) {
            throw new \RuntimeException(sprintf('Cannot handle archiver with type %s', $archiver->getType()));
        }

        if (!$archiver->isEnabled()) {
            throw new \RuntimeException(sprintf('Archiver %s is not enabled.', $archiver));
        }
    }
}
