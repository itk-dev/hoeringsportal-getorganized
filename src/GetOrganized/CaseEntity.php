<?php

namespace App\GetOrganized;

class CaseEntity extends Entity
{
    public string $id;
    public string $name;

    protected function build(array $data)
    {
        $this->id = $data['CaseID'];
        $this->name = $data['Name'];
    }
}
