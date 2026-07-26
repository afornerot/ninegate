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
                'description' => 'Bloc de notes rapide pour garder une idée, un pense-bête ou un commentaire.',
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
                'description' => 'Espace de stockage et de partage de fichiers.',
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
                'description' => 'Affiche vos images dans une galerie immersive.',
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
                'description' => 'Diaporama d\'images défilantes en plein écran.',
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
                'description' => 'Centre de navigation avec vos raccourcis, favoris et accès rapides.',
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
                'description' => 'Vos raccourcis et signets favoris toujours à portée de main.',
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
                'description' => 'Liste de liens web organisés et cliquables.',
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
                'description' => 'Agrège et affiche les actualités de vos flux RSS préférés.',
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
                'description' => 'Prévisions météo en temps réel pour la ville de votre choix.',
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
                'description' => 'Affiche l\'heure actuelle, avec option de plusieurs fuseaux horaires.',
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
                'description' => 'Derniers articles de blog publiés sur la plateforme.',
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
            [
                'title' => 'Annonces',
                'description' => 'Affiche les annonces accessibles à l\'utilisateur',
                'route' => 'pagewidget_annonce',
                'icon' => 'megaphone',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => false,
                'withTitle' => true,
                'height' => null,
                'hideIfEmpty' => true,
            ],
            [
                'title' => 'Tâches',
                'description' => 'Gestion de tâches (todo list)',
                'route' => 'pagewidget_tache',
                'icon' => 'clipboard',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => false,
                'withTitle' => true,
                'height' => null,
                'hideIfEmpty' => true,
                'config' => [
                    'mode' => ['type' => 'choice', 'label' => 'Mode', 'choices' => ['Mes tâches' => 'user', 'Tâches de la page' => 'linked'], 'default' => 'user'],
                ],
            ],
            [
                'title' => 'Calendrier',
                'description' => 'Affiche les prochains événements des calendriers',
                'route' => 'pagewidget_calendar',
                'icon' => 'calendar',
                'titleBgColor' => null,
                'titleFontColor' => null,
                'bodyBgColor' => null,
                'bodyFontColor' => null,
                'withBorder' => false,
                'withTitle' => true,
                'height' => null,
                'hideIfEmpty' => true,
                'config' => [
                    'mode' => ['type' => 'choice', 'label' => 'Mode', 'choices' => ['Mes calendriers' => 'user', 'Calendriers de la page' => 'linked'], 'default' => 'user'],
                    'nbEvents' => ['type' => 'number', 'label' => 'Nombre d\'événements', 'default' => 10],
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
            $widget->setDescription($item['description'] ?? null);
            $widget->setRoute($item['route']);
            $widget->setTitleBgColor($item['titleBgColor']);
            $widget->setTitleFontColor($item['titleFontColor']);
            $widget->setBodyBgColor($item['bodyBgColor']);
            $widget->setBodyFontColor($item['bodyFontColor']);
            $widget->setWithBorder($item['withBorder']);
            $widget->setWithTitle($item['withTitle'] ?? true);
            $widget->setHeight($item['height']);
            $widget->setHideIfEmpty($item['hideIfEmpty'] ?? false);

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
