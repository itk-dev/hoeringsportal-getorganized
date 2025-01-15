<?php

namespace App\GetOrganized;

class Document extends Entity
{
    /** @var string */
    public $docId;

    protected function build(array $data): void
    {
        $this->docId = $data['DocId'];
    }
}
