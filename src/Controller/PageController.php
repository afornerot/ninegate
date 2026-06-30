<?php

namespace App\Controller;

use App\Entity\Page;
use App\Repository\PageRepository;
use App\Repository\PageWidgetRepository;
use App\Voter\PageVoter;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    private const PAGE_PREFIX = '/page';
    private const ROUTE_PREFIX_USER = 'app_user_page';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_page';

    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepository,
        private PageWidgetRepository $pageWidgetRepository,
        private SlugService $slugService,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user'.self::PAGE_PREFIX, name: 'app_user_page_list')]
    #[Route('/admin'.self::PAGE_PREFIX, name: 'app_admin_page_list')]
    public function list(?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        if ($isAdmin) {
            $pages = $this->pageRepository->findAll();
        } else {
            $user = $this->getUser();
            $pages = $this->pageRepository->findAccessiblePages($user);
        }

        return $this->render('page/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $isAdmin ? 'Liste des Pages' : 'Mes Pages',
            'pages' => $pages,
            'isAdmin' => $isAdmin,
            'routesubmit' => $isAdmin ? 'app_admin_page_submit' : 'app_user_page_submit',
            'routeupdate' => $isAdmin ? 'app_admin_page_update' : 'app_user_page_update',
        ]);
    }

    #[Route('/user'.self::PAGE_PREFIX.'/submit', name: 'app_user_page_submit')]
    #[Route('/admin'.self::PAGE_PREFIX.'/submit', name: 'app_admin_page_submit')]
    public function submit(Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);
        $user = $this->getUser();

        $page = new Page();
        $formOptions = ['mode' => 'submit', 'isAdmin' => $isAdmin];

        if (!$isAdmin) {
            $formOptions['user'] = $user;
        }

        $form = $this->createForm(\App\Form\PageType::class, $page, $formOptions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (str_contains($_route, 'submit')) {
                $pageType = $form->get('pageType')->getData();
                if ($isAdmin) {
                    if ('personal' === $pageType) {
                        $page->setUser($form->get('user')->getData());
                        $page->clearGroups();
                        $page->setRoles([]);
                    } elseif ('group_role' === $pageType) {
                        $page->setUser(null);
                    }
                } else {
                    if ('personal' === $pageType) {
                        $page->setUser($user);
                        $page->clearGroups();
                        $page->setRoles([]);
                    } elseif ('group' === $pageType) {
                        $page->setUser(null);
                    }
                }
            }

            // Handle "Tout le monde" checkbox
            if ($form->has('allUsers') && $form->get('allUsers')->getData()) {
                $page->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $page->clearGroups();
                $page->setUser(null);
            }
            $this->em->persist($page);
            $page->setSlug($this->slugService->generateUniqueSlug($page->getTitle(), 'Page'));
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('page/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Page',
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_page_delete' : 'app_user_page_delete',
            'page' => $page,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user'.self::PAGE_PREFIX.'/update/{id}', name: 'app_user_page_update')]
    #[Route('/admin'.self::PAGE_PREFIX.'/update/{id}', name: 'app_admin_page_update')]
    public function update(int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $page = $this->pageRepository->find($id);
        if (!$page) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return $this->redirectToRoute($listRoute);
        }

        $user = $this->getUser();
        $formOptions = ['mode' => 'update', 'isAdmin' => $isAdmin];

        if (!$isAdmin) {
            $formOptions['user'] = $user;
        }

        $form = $this->createForm(\App\Form\PageType::class, $page, $formOptions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle "Tout le monde" checkbox
            if ($form->has('allUsers') && $form->get('allUsers')->getData()) {
                $page->setRoles(['ROLE_ADMIN', 'ROLE_MASTER', 'ROLE_USER', 'ROLE_VISITOR']);
                $page->clearGroups();
                $page->setUser(null);
            }

            $newSlug = $this->slugService->generateUniqueSlug($page->getTitle(), 'Page', $page->getId());
            if ($newSlug !== $page->getSlug()) {
                $page->setSlug($newSlug);
            }

            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('page/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Page = '.$page->getTitle(),
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_page_delete' : 'app_user_page_delete',
            'page' => $page,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user'.self::PAGE_PREFIX.'/delete/{id}', name: 'app_user_page_delete')]
    #[Route('/admin'.self::PAGE_PREFIX.'/delete/{id}', name: 'app_admin_page_delete')]
    public function delete(int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $page = $this->pageRepository->find($id);
        if (!$page) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(PageVoter::DELETE, $page)) {
            return $this->redirectToRoute($listRoute);
        }

        try {
            $this->em->remove($page);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            $routePrefix = $isAdmin ? self::ROUTE_PREFIX_ADMIN : self::ROUTE_PREFIX_USER;

            return $this->redirectToRoute($routePrefix.'_update', ['id' => $id]);
        }

        return $this->redirectToRoute($listRoute);
    }

    #[Route('/user'.self::PAGE_PREFIX.'/view/{slug}', name: 'app_user_page_view')]
    #[Route('/admin'.self::PAGE_PREFIX.'/view/{slug}', name: 'app_admin_page_view')]
    #[Route('/page/{slug}', name: 'app_page_view')]
    public function view(string $slug, ?string $_route = null): Response
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $page = $this->pageRepository->findOneBy(['slug' => $slug]);
        if (!$page) {
            throw $this->createNotFoundException('Page non trouvée');
        }

        if (!$this->isGranted(PageVoter::VIEW, $page)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page');
        }

        $widgets = $this->pageWidgetRepository->findBy(['page' => $page], ['widgetOrder' => 'ASC']);

        return $this->render('page/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => $page->getTitle(),
            'page' => $page,
            'widgets' => $widgets,
            'isAdmin' => $isAdmin,
        ]);
    }
}
