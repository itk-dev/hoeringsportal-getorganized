<?php

/*
 * This file is part of hoeringsportal-sync-files.
 *
 * (c) 2018–2019 ITK Development
 *
 * This source file is subject to the MIT license.
 */

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
