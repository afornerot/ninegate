<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ColorExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('hex_to_rgb', [$this, 'hexToRgb']),
        ];
    }

    public function hexToRgb(?string $hex): string
    {
        if (!$hex) {
            return '0, 0, 0';
        }

        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        
        if (strlen($hex) !== 6) {
            return '0, 0, 0';
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "$r, $g, $b";
    }
}