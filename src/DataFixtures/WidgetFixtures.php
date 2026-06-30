<?php

namespace App\DataFixtures;

use App\Entity\Icon;
use App\Entity\Widget;
use App\Repository\IconRepository;
use App\Repository\WidgetRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WidgetFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private WidgetRepository $widgetRepository,
        private IconRepository $iconRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'title' => 'Notes',
                'route' => 'pagewidget_note',
                'icon' => 'pencil',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => '#fff9c4',
                'bodyFontColor' => '#333333',
                'withBorder' => true,
                'withTitle' => false,
                'height' => null,
            ],
            [
                'title' => 'Fichiers',
                'route' => 'pagewidget_file',
                'icon' => 'folder',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
            ],
            [
                'title' => 'Galerie',
                'route' => 'pagewidget_gallery',
                'icon' => 'image',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => false,
                'withTitle' => false,
                'height' => null,
            ],
            [
                'title' => 'Carousel',
                'route' => 'pagewidget_carousel',
                'icon' => 'slr',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => false,
                'withTitle' => false,
                'height' => 400,
            ],
            [
                'title' => 'Bureau',
                'route' => 'pagewidget_bureau',
                'icon' => 'computer',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'categoryId' => ['type' => 'entity', 'class' => 'App\\Entity\\ItemCategory', 'label' => 'Catégorie', 'placeholder' => 'Toutes les catégories'],
                    'display' => ['type' => 'choice', 'label' => 'Taille des items', 'choices' => ['Petit' => 'small', 'Moyen' => 'medium', 'Grand' => 'large', 'Liste' => 'list'], 'default' => 'medium'],
                    'showFavorites' => ['type' => 'checkbox', 'label' => 'Afficher les favoris en premier', 'default' => true],
                    'showSearch' => ['type' => 'checkbox', 'label' => 'Afficher la zone de recherche', 'default' => true],
                    'showCategoryNav' => ['type' => 'checkbox', 'label' => 'Afficher la navbar catégories', 'default' => true],
                ],
            ],
            [
                'title' => 'Favoris',
                'route' => 'pagewidget_bookmark',
                'icon' => 'pin',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'display' => ['type' => 'choice', 'label' => 'Taille', 'choices' => ['Petit' => 'small', 'Moyen' => 'medium', 'Grand' => 'large', 'Liste' => 'list'], 'default' => 'medium'],
                ],
            ],
            [
                'title' => 'Liens',
                'route' => 'pagewidget_link',
                'icon' => 'globe',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
            ],
        ];

        foreach ($data as $item) {
            $widget = $this->widgetRepository->findOneBy(['route' => $item['route']]);

            if (!$widget) {
                $widget = new Widget();
                $manager->persist($widget);
            }

            $widget->setTitle($item['title']);
            $widget->setRoute($item['route']);
            $widget->setTitleBgColor($item['titleBgColor']);
            $widget->setTitleFontColor($item['titleFontColor']);
            $widget->setBodyBgColor($item['bodyBgColor']);
            $widget->setBodyFontColor($item['bodyFontColor']);
            $widget->setWithBorder($item['withBorder']);
            $widget->setWithTitle($item['withTitle'] ?? true);
            $widget->setHeight($item['height']);

            // Set config if present
            if (!empty($item['config'])) {
                $widget->setConfig($item['config']);
            }

            // Set icon by tag
            if (!empty($item['icon'])) {
                $icon = $this->iconRepository->findOneBy(['tags' => $item['icon']]);
                if ($icon) {
                    $widget->setIcon($icon);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [IconFixtures::class];
    }
}
