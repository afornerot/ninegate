<?php

namespace App\Controller;

use App\Entity\Icon;
use App\Form\IconType;
use App\Repository\IconRepository;
use App\Voter\IconVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IconController extends AbstractController
{
    private const ICON_PREFIX = '/icon';
    private const ROUTE_PREFIX_USER = 'app_user_icon';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_icon';

    public function __construct(
        private EntityManagerInterface $em,
        private IconRepository $iconRepository,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user'.self::ICON_PREFIX, name: 'app_user_icon_list')]
    #[Route('/admin'.self::ICON_PREFIX, name: 'app_admin_icon_list')]
    public function list(?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $routePrefix = $isAdmin ? self::ROUTE_PREFIX_ADMIN : self::ROUTE_PREFIX_USER;

        if ($isAdmin) {
            $icons = $this->iconRepository->findBy(['user' => null]);
        } else {
            $user = $this->getUser();
            $icons = $this->iconRepository->findBy(['user' => $user]);
        }

        return $this->render('icon/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $isAdmin ? 'Icônes' : 'Mes Icônes',
            'icons' => $icons,
            'admin' => $isAdmin,
            'routesubmit' => $routePrefix.'_submit',
            'routeupdate' => $routePrefix.'_update',
            'routedelete' => $routePrefix.'_delete',
        ]);
    }

    #[Route('/user'.self::ICON_PREFIX.'/submit', name: 'app_user_icon_submit')]
    #[Route('/admin'.self::ICON_PREFIX.'/submit', name: 'app_admin_icon_submit')]
    public function submit(Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $icon = new Icon();
        if (!$isAdmin) {
            $icon->setUser($this->getUser());
        }

        $form = $this->createForm(IconType::class, $icon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($icon);
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('icon/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvelle Icône',
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_icon_delete' : 'app_user_icon_delete',
            'icon' => $icon,
        ]);
    }

    #[Route('/user'.self::ICON_PREFIX.'/update/{id}', name: 'app_user_icon_update')]
    #[Route('/admin'.self::ICON_PREFIX.'/update/{id}', name: 'app_admin_icon_update')]
    public function update(int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $icon = $this->iconRepository->find($id);
        if (!$icon) {
            return $this->redirectToRoute($listRoute);
        }
        if (!$this->isGranted(IconVoter::EDIT, $icon)) {
            return $this->redirectToRoute($listRoute);
        }

        $form = $this->createForm(IconType::class, $icon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('icon/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier Icône = '.$icon->getRoute(),
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_icon_delete' : 'app_user_icon_delete',
            'icon' => $icon,
        ]);
    }

    #[Route('/user'.self::ICON_PREFIX.'/delete/{id}', name: 'app_user_icon_delete')]
    #[Route('/admin'.self::ICON_PREFIX.'/delete/{id}', name: 'app_admin_icon_delete')]
    public function delete(int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $icon = $this->iconRepository->find($id);
        if (!$icon) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(IconVoter::DELETE, $icon)) {
            return $this->redirectToRoute($listRoute);
        }

        $this->em->remove($icon);
        $this->em->flush();

        return $this->redirectToRoute($listRoute);
    }
}
