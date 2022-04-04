<?php

namespace App\Util;

use Twig\Environment;

class TemplateHelper
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function render(string $template, array $data)
    {
        return $this->twig->createTemplate($template)->render($data);
    }
}
