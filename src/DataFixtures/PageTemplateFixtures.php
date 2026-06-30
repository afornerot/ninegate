<?php

namespace App\DataFixtures;

use App\Entity\PageTemplate;
use App\Repository\PageTemplateRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PageTemplateFixtures extends Fixture
{
    public function __construct(
        private PageTemplateRepository $pageTemplateRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'name' => 'Grille Simple 3 Colonnes',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr 2fr 1fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
    <div id="R1C3"></div>
</div>',
            ],
            [
                'name' => 'Deux Colonnes Égales',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr 1fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
</div>',
            ],
            [
                'name' => 'Colonne Large + Colonne Petite',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 2fr 1fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
</div>',
            ],
            [
                'name' => 'Colonne Petite + Colonne Large',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr 2fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
</div>',
            ],
            [
                'name' => 'Grille 4 Colonnes',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
    <div id="R1C3"></div>
    <div id="R1C4"></div>
</div>',
            ],
            [
                'name' => 'Grille 2 Lignes (2+1)',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr 1fr;">
    <div id="R1C1"></div>
    <div id="R1C2"></div>
</div>
<div class="page-grid" style="grid-template-columns: 1fr;">
    <div id="R2C1"></div>
</div>',
            ],
            [
                'name' => 'Grille L Pleine + 3 Sous-colonnes',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr;">
    <div id="R1C1"></div>
</div>
<div class="page-grid" style="grid-template-columns: 1fr 1fr 1fr;">
    <div id="R2C1"></div>
    <div id="R2C2"></div>
    <div id="R2C3"></div>
</div>',
            ],
            [
                'name' => 'Grille 3 Lignes (1+2+1)',
                'logo' => 'medias/icon/grid.png',
                'template' => '<div class="page-grid" style="grid-template-columns: 1fr;">
    <div id="R1C1"></div>
</div>
<div class="page-grid" style="grid-template-columns: 1fr 1fr;">
    <div id="R2C1"></div>
    <div id="R2C2"></div>
</div>
<div class="page-grid" style="grid-template-columns: 1fr;">
    <div id="R3C1"></div>
</div>',
            ],
        ];

        foreach ($data as $item) {
            $template = $this->pageTemplateRepository->findOneBy(['name' => $item['name']]);

            if (!$template) {
                $template = new PageTemplate();
                $template->setName($item['name']);

                $manager->persist($template);
            }

            $template->setLogo($item['logo']);
            $template->setTemplate($item['template']);
        }

        $manager->flush();
    }
}
