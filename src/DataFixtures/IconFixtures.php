<?php

namespace App\DataFixtures;

use App\Entity\Icon;
use App\Repository\IconRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class IconFixtures extends Fixture
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private IconRepository $iconRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $iconDir = $this->parameterBag->get('kernel.project_dir') . '/public/medias/icon';

        if (!is_dir($iconDir)) {
            return;
        }

        $files = glob($iconDir . '/*');
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);
            $route = 'medias/icon/' . $filename;
            $tags = pathinfo($filename, PATHINFO_FILENAME);
            $tags = preg_replace('/^icon_/', '', $tags);

            $existingIcon = $this->iconRepository->findOneByRoute($route);
            if ($existingIcon) {
                continue;
            }

            $icon = new Icon();
            $icon->setRoute($route);
            $icon->setTags($tags);
            $manager->persist($icon);
        }

        $manager->flush();
    }
}