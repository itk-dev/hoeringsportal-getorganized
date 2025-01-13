<?php

namespace App\Util;

use Twig\Environment;

class TemplateHelper
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $template, array $data)
    {
        return $this->twig->createTemplate($template)->render($data);
    }
}
