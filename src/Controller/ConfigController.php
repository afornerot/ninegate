<?php

namespace App\Controller;

use App\Entity\Config;
use App\Form\ConfigType;
use App\Repository\ConfigRepository;
use App\Service\ConfigParameterBag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfigController extends AbstractController
{
    private const ROUTE_PREFIX = 'app_admin_config';

    public function __construct(
        private EntityManagerInterface $em,
        private ConfigRepository $configRepository,
    ) {
    }

    #[Route('/admin/config', name: self::ROUTE_PREFIX)]
    public function list(): Response
    {
        $configs = $this->configRepository->findAll();

        return $this->render('config/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Configuration',
            'configs' => $configs,
            'routeupdate' => self::ROUTE_PREFIX.'_update',
        ]);
    }

    #[Route('/admin/config/update/{id}', name: self::ROUTE_PREFIX.'_update')]
    public function update(int $id, Request $request, ConfigParameterBag $configParameterBag): Response
    {
        $config = $this->configRepository->find($id);
        if (!$config) {
            return $this->redirectToRoute(self::ROUTE_PREFIX);
        }

        $form = $this->createForm(ConfigType::class, $config);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $rawValue = $form->get('rawValue')->getData();
            $config->setRawValue($rawValue);
            $this->em->flush();
            $configParameterBag->reload();

            return $this->redirectToRoute(self::ROUTE_PREFIX);
        }

        return $this->render('config/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier Configuration = '.$config->getCode(),
            'config' => $config,
            'routecancel' => self::ROUTE_PREFIX,
            'routedelete' => self::ROUTE_PREFIX.'_delete',
            'form' => $form,
        ]);
    }

    #[Route('/admin/config/delete/{id}', name: self::ROUTE_PREFIX.'_delete')]
    public function delete(int $id, ConfigParameterBag $configParameterBag): Response
    {
        $config = $this->configRepository->find($id);
        if (!$config) {
            return $this->redirectToRoute(self::ROUTE_PREFIX);
        }

        $config->setValue(null);
        $this->em->flush();
        $configParameterBag->reload();

        return $this->redirectToRoute(self::ROUTE_PREFIX);
    }
}