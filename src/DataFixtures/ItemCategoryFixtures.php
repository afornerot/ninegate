<?php

namespace App\DataFixtures;

use App\Entity\ItemCategory;
use App\Repository\ItemCategoryRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ItemCategoryFixtures extends Fixture
{
    public function __construct(
        private ItemCategoryRepository $itemCategoryRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            ['title' => 'Applications', 'sortOrder' => 0],
            ['title' => 'Administration', 'sortOrder' => 99],
        ];

        foreach ($data as $item) {
            $category = $this->itemCategoryRepository->findOneBy(['title' => $item['title']]);

            if (!$category) {
                $category = new ItemCategory();
                $manager->persist($category);
            }

            $category->setTitle($item['title']);
            $category->setSortOrder($item['sortOrder']);
        }

        $manager->flush();
    }
}
