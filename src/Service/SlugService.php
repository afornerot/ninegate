<?php

namespace App\Service;

use App\Repository\BlogArticleRepository;
use App\Repository\BlogRepository;
use App\Repository\PageRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

class SlugService
{
    public function __construct(
        private SluggerInterface $slugger,
        private PageRepository $pageRepository,
        private BlogRepository $blogRepository,
        private BlogArticleRepository $blogArticleRepository,
    ) {
    }

    public function generateUniqueSlug(string $title, string $entityClass, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $entityClass, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, string $entityClass, ?int $excludeId): bool
    {
        $entity = match ($entityClass) {
            'Page' => $this->pageRepository->findOneBy(['slug' => $slug]),
            'Blog' => $this->blogRepository->findOneBy(['slug' => $slug]),
            'BlogArticle' => $this->blogArticleRepository->findOneBy(['slug' => $slug]),
            default => null,
        };

        if (!$entity) {
            return false;
        }

        if ($excludeId && method_exists($entity, 'getId') && $entity->getId() === $excludeId) {
            return false;
        }

        return true;
    }
}
