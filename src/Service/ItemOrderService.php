<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\ItemCategory;
use App\Repository\ItemCategoryRepository;
use App\Repository\ItemRepository;
use Collator;

/**
 * Gestion de l'ordre d'affichage des items dans une catégorie.
 *
 * L'ordre est porté par la colonne item.sort_order : deux items d'une même
 * catégorie ne doivent jamais partager la même valeur, sinon l'ordre affiché
 * devient indéterminé.
 */
class ItemOrderService
{
    public function __construct(
        private ItemRepository $itemRepository,
        private ItemCategoryRepository $itemCategoryRepository,
    ) {
    }

    /**
     * Applique l'ordre demandé sur les items des catégories concernées.
     *
     * Chaque liste reçue est l'ordre complet et définitif des items de la
     * catégorie : les items listés y sont rattachés et numérotés dans l'ordre
     * donné. Les items de la catégorie absents de la liste y restent et sont
     * numérotés après les items listés.
     *
     * @param array<int, int[]> $orders identifiant de catégorie => identifiants d'items ordonnés
     *
     * @return array<int, int[]> identifiant de catégorie => identifiants d'items dans l'ordre effectif
     */
    public function applyOrders(array $orders): array
    {
        $categories = [];
        $lists = [];
        $assigned = [];

        foreach ($orders as $categoryId => $itemIds) {
            $category = $this->itemCategoryRepository->find((int) $categoryId);
            if (!$category || !is_array($itemIds)) {
                continue;
            }

            $ids = [];
            foreach ($itemIds as $itemId) {
                $itemId = (int) $itemId;
                $item = $this->itemRepository->find($itemId);
                // Un item ne peut être listé que dans une seule catégorie
                if (!$item || isset($assigned[$itemId])) {
                    continue;
                }
                $ids[] = $itemId;
                $assigned[$itemId] = (int) $categoryId;
            }

            $categories[(int) $categoryId] = $category;
            $lists[(int) $categoryId] = $ids;
        }

        $applied = [];

        foreach ($lists as $categoryId => $ids) {
            $category = $categories[$categoryId];
            $result = [];

            foreach ($ids as $index => $itemId) {
                $item = $this->itemRepository->find($itemId);
                $item->setCategory($category);
                $item->setSortOrder($index);
                $result[] = $itemId;
            }

            // Les items de la catégorie qui ne figurent pas dans la liste restent
            // en place mais passent après les items listés
            $remaining = $this->itemRepository->findBy(['category' => $category], ['sortOrder' => 'ASC', 'id' => 'ASC']);
            foreach ($remaining as $item) {
                $itemId = $item->getId();
                if (isset($assigned[$itemId]) || $item->getCategory() !== $category) {
                    continue;
                }
                $item->setSortOrder(count($result));
                $result[] = $itemId;
            }

            $applied[$categoryId] = $result;
        }

        return $applied;
    }

    /**
     * Range les items d'une catégorie par ordre alphabétique de leur titre.
     *
     * @return int[] identifiants des items dans l'ordre alphabétique
     */
    public function sortAlphabetically(ItemCategory $category): array
    {
        $items = $this->itemRepository->findBy(['category' => $category], ['sortOrder' => 'ASC', 'id' => 'ASC']);

        $collator = $this->createCollator();
        usort($items, function (Item $a, Item $b) use ($collator): int {
            $titleA = (string) $a->getTitle();
            $titleB = (string) $b->getTitle();

            $comparison = $collator ? $collator->compare($titleA, $titleB) : strnatcasecmp($titleA, $titleB);
            if (0 !== $comparison) {
                return $comparison;
            }

            return $a->getId() <=> $b->getId();
        });

        $ids = array_map(fn (Item $item) => $item->getId(), $items);
        $applied = $this->applyOrders([$category->getId() => $ids]);

        return $applied[$category->getId()] ?? $ids;
    }

    /**
     * Ordre à attribuer à un nouvel item pour qu'il arrive en fin de catégorie.
     */
    public function nextSortOrder(ItemCategory $category): int
    {
        $max = 0;
        foreach ($this->itemRepository->findBy(['category' => $category]) as $item) {
            $max = max($max, $item->getSortOrder());
        }

        return $max + 1;
    }

    private function createCollator(): ?Collator
    {
        if (!class_exists(Collator::class)) {
            return null;
        }

        $collator = new Collator('fr');
        // Ordre alphabétique : insensible à la casse, sensible aux accents
        $collator->setStrength(Collator::SECONDARY);

        return $collator;
    }
}
