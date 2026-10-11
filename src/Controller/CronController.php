<?php

namespace App\Controller;

use App\Entity\Cron;
use App\Form\CronType;
use App\Repository\CronRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CronController extends AbstractController
{
    public function __construct(
        private CronRepository $cronRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/cron', name: 'app_admin_cron')]
    public function list(): Response
    {
        $crons = $this->cronRepository->findAll();

        return $this->render('cron/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Liste des Crons',
            'routeupdate' => 'app_admin_cron_update',
            'routedelete' => 'app_admin_cron_delete',
            'crons' => $crons,
        ]);
    }

    #[Route('/admin/cron/update/{id}', name: 'app_admin_cron_update')]
    public function update(int $id, Request $request): Response
    {
        $cron = $this->cronRepository->find($id);
        if (!$cron) {
            return $this->redirectToRoute('app_admin_cron');
        }

        $form = $this->createForm(CronType::class, $cron, ['mode' => 'update']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_admin_cron');
        }

        return $this->render('cron/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Cron = '.$cron->getCommand(),
            'routecancel' => 'app_admin_cron',
            'routedelete' => 'app_admin_cron_delete',
            'mode' => 'update',
            'form' => $form,
            'cron' => $cron,
        ]);
    }

    #[Route('/admin/cron/delete/{id}', name: 'app_admin_cron_delete')]
    public function delete(int $id): Response
    {
        $cron = $this->cronRepository->find($id);
        if (!$cron) {
            return $this->redirectToRoute('app_admin_cron');
        }

        // Tentative de suppression
        try {
            $this->em->remove($cron);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addflash('error', $e->getMessage());

            return $this->redirectToRoute('app_admin_cron_update', ['id' => $id]);
        }

        return $this->redirectToRoute('app_admin_cron');
    }
}
