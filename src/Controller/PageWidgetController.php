<?php

namespace App\Controller;

use App\Entity\PageWidget;
use App\Repository\PageRepository;
use App\Repository\PageWidgetRepository;
use App\Voter\PageVoter;
use App\Voter\WidgetVoter;
use App\Service\WidgetConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageWidgetController extends AbstractController
{
    private const pagewidget_PREFIX = '/page';
    private const ROUTE_PREFIX_USER = 'app_user_pagewidget';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_pagewidget';

    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepository,
        private PageWidgetRepository $pageWidgetRepository,
        private WidgetConfigService $widgetConfigService,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/{pageId}', name: 'app_user_pagewidget_list')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/{pageId}', name: 'app_admin_pagewidget_list')]
    public function list(int $pageId, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_page_list' : 'app_user_page_list');
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_page_list' : 'app_user_page_list');
        }

        $widgets = $this->pageWidgetRepository->findBy(['page' => $page], ['widgetOrder' => 'ASC']);

        return $this->render('pagewidget/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Widgets de la page : '.$page->getTitle(),
            'page' => $page,
            'widgets' => $widgets,
            'admin' => $isAdmin,
            'routemove' => $isAdmin ? 'app_admin_pagewidget_move' : 'app_user_pagewidget_move',
            'routesubmit' => $isAdmin ? 'app_admin_pagewidget_submit' : 'app_user_pagewidget_submit',
            'routeupdate' => $isAdmin ? 'app_admin_pagewidget_update' : 'app_user_pagewidget_update',
            'routedelete' => $isAdmin ? 'app_admin_pagewidget_delete' : 'app_user_pagewidget_delete',
        ]);
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/submit/{pageId}', name: 'app_user_pagewidget_submit')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/submit/{pageId}', name: 'app_admin_pagewidget_submit')]
    public function submit(int $pageId, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return $this->redirectToRoute($listRoute);
        }

        $widget = new PageWidget();
        $widget->setPage($page);

        $formOptions = ['mode' => 'submit', 'isAdmin' => $isAdmin];

        $form = $this->createForm(\App\Form\PageWidgetType::class, $widget, $formOptions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $selectedWidget = $widget->getWidget();
            if ($selectedWidget) {
                $widget->setTitle($selectedWidget->getTitle());
                $widget->setTitleBgColor($selectedWidget->getTitleBgColor());
                $widget->setTitleFontColor($selectedWidget->getTitleFontColor());
                $widget->setBodyBgColor($selectedWidget->getBodyBgColor());
                $widget->setBodyFontColor($selectedWidget->getBodyFontColor());
                $widget->setWithBorder($selectedWidget->isWithBorder());
                $widget->setWithTitle($selectedWidget->isWithTitle());
                $widget->setHeight($selectedWidget->getHeight());
                $widget->setIcon($selectedWidget->getIcon());
            }

            $this->em->persist($widget);
            $this->em->flush();

            $updateRoute = $isAdmin ? 'app_admin_pagewidget_update' : 'app_user_pagewidget_update';
            $updateUrl = $this->generateUrl($updateRoute, ['pageId' => $pageId, 'id' => $widget->getId()]);

            if ($request->query->get('partial')) {
                return new JsonResponse(['success' => true, 'redirect' => $updateUrl]);
            }

            return $this->redirectToRoute($updateRoute, ['pageId' => $pageId, 'id' => $widget->getId()]);
        }

        if ($request->query->get('partial')) {
            return $this->render('pagewidget/_widget_select.html.twig', [
                'form' => $form->createView(),
                'form_action' => $this->generateUrl($_route, ['pageId' => $pageId]),
            ]);
        }

        return $this->render('pagewidget/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Ajout d\'un Widget',
            'form' => $form,
            'routecancel' => $listRoute,
            'page' => $page,
            'widget' => $widget,
            'isAdmin' => $isAdmin,
            'pageId' => $pageId,
        ]);
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/update/{pageId}/{id}', name: 'app_user_pagewidget_update')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/update/{pageId}/{id}', name: 'app_admin_pagewidget_update')]
    public function update(int $pageId, int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return $this->redirectToRoute($listRoute);
        }

        $widget = $this->pageWidgetRepository->find($id);
        if (!$widget || $widget->getPage()->getId() !== $pageId) {
            return $this->redirectToRoute($listRoute, ['pageId' => $pageId]);
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return $this->redirectToRoute($listRoute);
        }

        $formOptions = ['mode' => 'update', 'isAdmin' => $isAdmin];

        $form = $this->createForm(\App\Form\PageWidgetType::class, $widget, $formOptions);

        // Generic widget config form
        $configForm = null;
        $widgetDef = $widget->getWidget();
        $configFields = $widgetDef?->getConfig() ?? [];
        if (!empty($configFields)) {
            $widgetDefaults = $this->widgetConfigService->getDefaults($configFields);

            if (($widgetDef?->getRoute() ?? '') === 'pagewidget_blog' && $page->getGroups()->count() > 0) {
                $widgetDefaults['mode'] = 'linked';
            }

            $instanceConfig = $widget->getContent() ?? [];
            $mergedConfig = array_merge($widgetDefaults, $instanceConfig);

            $configForm = $this->widgetConfigService->buildConfigForm($configFields, $mergedConfig);
            if ($configForm) {
                $configForm->handleRequest($request);
            }
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Save widget config if applicable
            if ($configForm && $configForm->isSubmitted() && $configForm->isValid()) {
                $configData = $configForm->getData();
                foreach ($configData as $key => $value) {
                    if (isset($configFields[$key]) && ($configFields[$key]['type'] ?? '') === 'entity') {
                        // Convert entity object to ID for JSON storage
                        if ($value === null) {
                            $configData[$key] = null;
                        } elseif (is_object($value) && method_exists($value, 'getId')) {
                            $configData[$key] = $value->getId();
                        }
                    }
                }
                $widget->setContent($configData);
            }

            $this->em->flush();

            return $this->redirectToRoute($listRoute, ['pageId' => $pageId]);
        }

        return $this->render('pagewidget/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Modification du Widget',
            'form' => $form,
            'configForm' => $configForm,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_pagewidget_delete' : 'app_user_pagewidget_delete',
            'page' => $page,
            'widget' => $widget,
            'isAdmin' => $isAdmin,
            'pageId' => $pageId,
        ]);
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/delete/{pageId}/{id}', name: 'app_user_pagewidget_delete')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/delete/{pageId}/{id}', name: 'app_admin_pagewidget_delete')]
    public function delete(int $pageId, int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return $this->redirectToRoute($listRoute);
        }

        $widget = $this->pageWidgetRepository->find($id);
        if (!$widget || $widget->getPage()->getId() !== $pageId) {
            return $this->redirectToRoute($listRoute, ['pageId' => $pageId]);
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return $this->redirectToRoute($listRoute);
        }

        try {
            $widgetRoute = $widget->getWidget()?->getRoute();
            $pageWidgetId = $widget->getId();

            $this->em->remove($widget);
            $this->em->flush();

            if (in_array($widgetRoute, ['pagewidget_file', 'pagewidget_gallery', 'pagewidget_carousel'])) {
                $uploadDir = $this->getParameter('kernel.project_dir').'/uploads/pagewidgetfile/'.$pageWidgetId;
                $fs = new Filesystem();
                if ($fs->exists($uploadDir)) {
                    $fs->remove($uploadDir);
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            $routePrefix = $isAdmin ? self::ROUTE_PREFIX_ADMIN : self::ROUTE_PREFIX_USER;

            return $this->redirectToRoute($routePrefix.'_update', ['pageId' => $pageId, 'id' => $id]);
        }

        return $this->redirectToRoute($listRoute, ['pageId' => $pageId]);
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/render/{pageId}/{id}', name: 'app_user_pagewidget_render')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/render/{pageId}/{id}', name: 'app_admin_pagewidget_render')]
    public function renderWidget(int $pageId, int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return new Response('');
        }

        $pageWidget = $this->pageWidgetRepository->find($id);
        if (!$pageWidget || $pageWidget->getPage()->getId() !== $pageId) {
            return new Response('');
        }

        $widget = $pageWidget->getWidget();
        if (!$widget) {
            return new Response('');
        }

        $prefix = $isAdmin ? 'app_admin' : 'app_user';
        $widgetRoute = $prefix.'_'.$widget->getRoute();

        $user = $this->getUser();
        $canManage = $isAdmin && $user && $user->hasRole('ROLE_ADMIN')
            ? true
            : $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        return $this->render('pagewidget/render.html.twig', [
            'pageWidget' => $pageWidget,
            'widget' => $widget,
            'widgetRoute' => $widgetRoute,
            'pageId' => $pageId,
            'canManage' => $canManage,
        ]);
    }

    #[Route('/user'.self::pagewidget_PREFIX.'/pagewidget/move/{pageId}/{id}', name: 'app_user_pagewidget_move')]
    #[Route('/admin'.self::pagewidget_PREFIX.'/pagewidget/move/{pageId}/{id}', name: 'app_admin_pagewidget_move')]
    public function move(int $pageId, int $id, Request $request, ?string $_route): JsonResponse
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $page = $this->pageRepository->find($pageId);
        if (!$page) {
            return new JsonResponse(['success' => false, 'error' => 'Page introuvable'], 404);
        }

        $widget = $this->pageWidgetRepository->find($id);
        if (!$widget || $widget->getPage()->getId() !== $pageId) {
            return new JsonResponse(['success' => false, 'error' => 'Widget introuvable'], 404);
        }

        if (!$this->isGranted(PageVoter::EDIT, $page)) {
            return new JsonResponse(['success' => false, 'error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $location = $data['location'] ?? null;
        $widgetOrder = $data['widgetOrder'] ?? null;
        $widgetsData = $data['widgets'] ?? null;

        if ($location && preg_match('/^R\d{1,2}C\d{1,2}$/', $location)) {
            $widget->setLocation($location);
        }

        if ($widgetOrder !== null) {
            $widget->setWidgetOrder((int) $widgetOrder);
        }

        // Update orders for all widgets in the target cell
        if (is_array($widgetsData)) {
            foreach ($widgetsData as $wData) {
                $wId = $wData['id'] ?? null;
                $wOrder = $wData['widgetOrder'] ?? null;
                $wLocation = $wData['location'] ?? null;
                if ($wId !== null && $wOrder !== null) {
                    $w = $this->pageWidgetRepository->find($wId);
                    if ($w && $w->getPage()->getId() === $pageId) {
                        $w->setWidgetOrder((int) $wOrder);
                        if ($wLocation && preg_match('/^R\d{1,2}C\d{1,2}$/', $wLocation)) {
                            $w->setLocation($wLocation);
                        }
                    }
                }
            }
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'location' => $location]);
    }
}
