<?php

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Service\ConfigParameterBag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

class ThemeController extends AbstractController
{
    private const ROUTE_PREFIX = 'app_admin_theme';

    private const THEME_PATH = 'medias/theme';

    public function __construct(
        private EntityManagerInterface $em,
        private ConfigRepository $configRepository,
        private ConfigParameterBag $configParameterBag,
    ) {
    }

    #[Route('/admin/theme', name: self::ROUTE_PREFIX)]
    public function list(): Response
    {
        $themeDir = $this->getParameter('kernel.project_dir').'/public/'.self::THEME_PATH;
        $themes = [];

        if (is_dir($themeDir)) {
            $files = glob($themeDir.'/*.yaml');
            foreach ($files as $file) {
                $theme = Yaml::parseFile($file);
                $themes[basename($file, '.yaml')] = $theme;
            }
        }

        return $this->render('theme/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Thèmes',
            'themes' => $themes,
            'routeapply' => self::ROUTE_PREFIX.'_apply',
        ]);
    }

    #[Route('/admin/theme/apply/{themeName}', name: self::ROUTE_PREFIX.'_apply')]
    public function apply(string $themeName): Response
    {
        $themeFile = $this->getParameter('kernel.project_dir').'/public/'.self::THEME_PATH.'/'.$themeName.'.yaml';

        if (!file_exists($themeFile)) {
            $this->addFlash('danger', 'Thème non trouvé');

            return $this->redirectToRoute(self::ROUTE_PREFIX);
        }

        $theme = Yaml::parseFile($themeFile);

        $configKeys = [
            'app-bs-primary' => 'primary',
            'app-bs-secondary' => 'secondary',
            'app-bs-success' => 'success',
            'app-bs-danger' => 'danger',
            'app-bs-warning' => 'warning',
            'app-bs-info' => 'info',
            'app-bs-light' => 'light',
            'app-bs-dark' => 'dark',
            'app-bs-body-bg' => 'body-bg',
            'app-bs-body-color' => 'body-color',
            'app-bs-body-color-dark' => 'body-color-dark',
            'app-bs-border-color' => 'border-color',
            'app-bs-card-bg' => 'card-bg',
            'app-bs-btn-bg' => 'btn-bg',
            'app-bs-btn-border-color' => 'btn-border-color',
            'appFontHeader' => 'fontHeader',
            'appFontBody' => 'fontBody',
            'app-bs-header' => 'app-bs-header',
        ];

        foreach ($configKeys as $configCode => $themeKey) {
            if (isset($theme[$themeKey])) {
                $config = $this->configRepository->findOneByCode($configCode);
                if ($config) {
                    $config->setValue($theme[$themeKey]);
                    $this->em->persist($config);
                }
            }
        }

        $this->em->flush();
        $this->configParameterBag->reload();

        $this->addFlash('success', 'Thème "'.($theme['name'] ?? $themeName).'" appliqué avec succès');

        return $this->redirectToRoute(self::ROUTE_PREFIX);
    }
}
