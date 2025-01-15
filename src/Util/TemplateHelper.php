<?php

namespace App\Util;

use Twig\Environment;

class TemplateHelper
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data): string
    {
        return $this->twig->createTemplate($template)->render($data);
    }
}
