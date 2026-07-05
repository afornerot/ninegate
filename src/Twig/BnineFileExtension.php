<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BnineFileExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('bninefile', [$this, 'getBnineFileUrl'], ['is_safe' => ['html']]),
        ];
    }

    public function getBnineFileUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return '/' . $path;
    }
}
