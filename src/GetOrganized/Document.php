<?php

namespace App\GetOrganized;

class Document extends Entity
{
    /** @var string */
    public $docId;

    protected function build(array $data)
    {
        $this->docId = $data['DocId'];
    }
}
