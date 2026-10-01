<?php

namespace App\Cache;

use Bnine\FilesBundle\Cache\CachePolicyInterface;

class FileCachePolicy implements CachePolicyInterface
{
    public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int
    {
        if (in_array($domain, ['avatar', 'logo', 'icon'], true)) {
            return 300;
        }

        if (str_contains($filePath, '_thumbs/')) {
            return 31536000;
        }

        return null;
    }

    public function isPublicCacheable(string $domain, string $id, string $filePath): ?bool
    {
        if (in_array($domain, ['avatar', 'logo', 'icon'], true)) {
            return false;
        }

        return null;
    }
}