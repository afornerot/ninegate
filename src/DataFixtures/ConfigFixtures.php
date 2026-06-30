<?php

namespace App\DataFixtures;

use App\Entity\Config;
use App\Repository\ConfigRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ConfigFixtures extends Fixture
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private ConfigRepository $configRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'code' => 'appName',
                'title' => 'Titre de l\'Application',
                'value' => null,
                'defaultValue' => $this->parameterBag->get('appName'),
                'type' => Config::TYPE_STRING,
                'configGroup' => 'Site',
                'order' => 0,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'appDescription',
                'title' => 'Description',
                'value' => null,
                'defaultValue' => 'Un portail pour tous',
                'type' => Config::TYPE_TEXT,
                'configGroup' => 'Site',
                'order' => 1,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'appLogo',
                'title' => 'Logo du site',
                'value' => null,
                'defaultValue' => 'medias/logo/logo.png',
                'type' => Config::TYPE_LOGO,
                'configGroup' => 'Site',
                'order' => 2,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-primary',
                'title' => 'Couleur principale - Buttons, liens principaux',
                'value' => null,
                'defaultValue' => '#375a7f',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 0,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-secondary',
                'title' => 'Couleur secondaire',
                'value' => null,
                'defaultValue' => '#444',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 1,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-success',
                'title' => 'Couleur succès - Vert',
                'value' => null,
                'defaultValue' => '#00bc8c',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 2,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-danger',
                'title' => 'Couleur danger - Rouge (erreurs)',
                'value' => null,
                'defaultValue' => '#e74c3c',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 3,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-warning',
                'title' => 'Couleur avertissement - Orange',
                'value' => null,
                'defaultValue' => '#f39c12',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 4,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-info',
                'title' => 'Couleur info - Bleu clair',
                'value' => null,
                'defaultValue' => '#3498db',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 5,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-light',
                'title' => 'Couleur clair - Fond clair',
                'value' => null,
                'defaultValue' => '#adb5bd',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 6,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-dark',
                'title' => 'Couleur sombre',
                'value' => null,
                'defaultValue' => '#303030',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 7,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-body-bg',
                'title' => 'Fond de la page',
                'value' => null,
                'defaultValue' => '#222',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 8,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-body-color',
                'title' => 'Couleur du texte principal',
                'value' => null,
                'defaultValue' => '#fff',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 9,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-border-color',
                'title' => 'Couleur des bordures',
                'value' => null,
                'defaultValue' => '#444',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 10,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-card-bg',
                'title' => 'Couleur de fond des cartes',
                'value' => null,
                'defaultValue' => '#303030',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 11,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-btn-bg',
                'title' => 'Couleur de fond des boutons',
                'value' => null,
                'defaultValue' => '#375a7f',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 12,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-btn-border-color',
                'title' => 'Couleur des bordures des boutons',
                'value' => null,
                'defaultValue' => '#375a7f',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 13,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-body-color-dark',
                'title' => 'Couleur du texte (version sombre)',
                'value' => null,
                'defaultValue' => '#fff',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorBg',
                'order' => 14,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'appFontHeader',
                'title' => 'Police des titres (Header)',
                'value' => null,
                'defaultValue' => 'Anton',
                'type' => Config::TYPE_FONT,
                'configGroup' => 'Font',
                'order' => 0,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'appFontBody',
                'title' => 'Police du texte (Body)',
                'value' => null,
                'defaultValue' => 'Roboto',
                'type' => Config::TYPE_FONT,
                'configGroup' => 'Font',
                'order' => 1,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
            [
                'code' => 'app-bs-header',
                'title' => 'Couleur des titres',
                'value' => null,
                'defaultValue' => '#ffffff',
                'type' => Config::TYPE_COLOR,
                'configGroup' => 'ColorTheme',
                'order' => 15,
                'configMasterCode' => null,
                'configMasterValue' => null,
            ],
        ];

        foreach ($data as $item) {
            $config = $this->configRepository->findOneByCode($item['code']);

            if (!$config) {
                $config = new Config();
                $config->setCode($item['code']);
                $config->setValue($item['value']);
            }

            $config->setTitle($item['title']);
            $config->setDefaultValue($item['defaultValue']);
            $config->setType($item['type']);
            $config->setConfigGroup($item['configGroup']);
            $config->setOrder($item['order']);
            $config->setConfigMasterCode($item['configMasterCode']);
            $config->setConfigMasterValue($item['configMasterValue']);

            $manager->persist($config);
        }

        $existingCodes = array_column($data, 'code');
        $allConfigs = $this->configRepository->findAll();
        foreach ($allConfigs as $config) {
            if (!in_array($config->getCode(), $existingCodes)) {
                $manager->remove($config);
            }
        }

        $manager->flush();
    }
}
