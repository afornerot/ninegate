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
            [
                'title' => 'Flux RSS',
                'route' => 'pagewidget_rss',
                'icon' => 'rss',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'feedUrls' => ['type' => 'textarea', 'label' => 'URLs des flux (une par ligne)', 'default' => ''],
                    'maxItems' => ['type' => 'number', 'label' => 'Nombre max d\'articles', 'default' => 10],
                    'showDescription' => ['type' => 'checkbox', 'label' => 'Afficher la description', 'default' => true],
                ],
            ],
            [
                'title' => 'Météo',
                'route' => 'pagewidget_weather',
                'icon' => 'cloud',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'latitude' => ['type' => 'hidden', 'label' => 'Latitude', 'default' => null],
                    'longitude' => ['type' => 'hidden', 'label' => 'Longitude', 'default' => null],
                    'city' => ['type' => 'text', 'label' => 'Ville', 'default' => ''],
                ],
            ],
            [
                'title' => 'Horloge',
                'route' => 'pagewidget_clock',
                'icon' => 'hourglass',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'timezone' => ['type' => 'choice', 'label' => 'Fuseau horaire principal', 'choices' => ['Paris' => 'Europe/Paris', 'Londres' => 'Europe/London', 'Berlin' => 'Europe/Berlin', 'New York' => 'America/New_York', 'Tokyo' => 'Asia/Tokyo'], 'default' => 'Europe/Paris'],
                    'extraTimezones' => ['type' => 'text', 'label' => 'Fuseaux horaires supplémentaires (séparés par des virgules)', 'default' => ''],
                ],
            ],
            [
                'title' => 'Blog',
                'route' => 'pagewidget_blog',
                'icon' => 'news',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => true,
                'withTitle' => true,
                'height' => null,
                'config' => [
                    'nbArticles' => ['type' => 'number', 'label' => 'Nombre d\'articles', 'default' => 10],
                    'mode' => ['type' => 'choice', 'label' => 'Mode', 'choices' => ['Tous les blogs' => 'all', 'Blogs liés à la page' => 'linked'], 'default' => 'all'],
                ],
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
