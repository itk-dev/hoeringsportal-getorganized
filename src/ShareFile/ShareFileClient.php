<?php

namespace App\ShareFile;

use Kapersoft\ShareFile\Client;

class ShareFileClient extends Client
{
    protected function get(string $endpoint)
    {
        $sessionKey = __METHOD__.':'.$endpoint;

        $result = $_SESSION[$sessionKey] ?? parent::get($endpoint);
        $_SESSION[$sessionKey] = $result;

        return $result;
    }
}
