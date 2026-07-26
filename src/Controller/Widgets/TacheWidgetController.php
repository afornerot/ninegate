<?php

namespace App\Controller\Widgets;

use App\Entity\Tache;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\GroupRepository;
use App\Repository\PageWidgetRepository;
use App\Repository\TacheRepository;
use App\Repository\UserRepository;
use App\Service\WidgetConfigService;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TacheWidgetController extends AbstractController
{
    public function __construct(
        private TacheRepository $tacheRepository,
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private WidgetConfigService $widgetConfigService,
    ) {
    }

    #[Route('/user/tache', name: 'app_user_tache_list')]
    public function list(): Response
    {
        $user = $this->getUser();
        $taches = $this->loadTaches($user, 'user', null);

        return $this->render('tache/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Mes Tâches',
            'taches' => $taches,
            'statuses' => \App\Entity\Tache::STATUSES,
        ]);
    }

    #[Route('/user/widget/tache/{pageWidgetId}', name: 'app_user_pagewidget_tache', methods: ['GET'], requirements: ['pageWidgetId' => '\d+'])]
    #[Route('/admin/widget/tache/{pageWidgetId}', name: 'app_admin_pagewidget_tache', methods: ['GET'], requirements: ['pageWidgetId' => '\d+'])]
    public function __invoke(string $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $pageWidget = $pageWidgetRepository->find((int) $pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);
        $user = $this->getUser();

        $configFields = $pageWidget->getWidget()->getConfig() ?? [];
        $instanceConfig = $pageWidget->getContent() ?? [];
        $widgetDefaults = $this->widgetConfigService->getDefaults($configFields);
        $config = array_merge($widgetDefaults, $instanceConfig);

        $mode = $config['mode'] ?? 'user';

        $taches = $this->loadTaches($user, $mode, $pageWidget);

        $pageGroups = [];
        if ($mode === 'linked') {
            $page = $pageWidget->getPage();
            foreach ($page->getGroups() as $group) {
                $pageGroups[] = ['id' => $group->getId(), 'name' => $group->getName()];
            }
        }

        return $this->render('widget/tache.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'taches' => $taches,
            'isAdmin' => $isAdmin,
            'statuses' => \App\Entity\Tache::STATUSES,
            'mode' => $mode,
            'pageGroups' => $pageGroups,
        ]);
    }

    #[Route('/user/tache/save', name: 'app_user_tache_save', methods: ['POST'])]
    public function saveStandalone(Request $request, EntityManagerInterface $em): JsonResponse
    {
        return $this->saveTache($request, $em, null);
    }

    #[Route('/user/widget/tache/save/{pageWidgetId}', name: 'app_user_pagewidget_tache_save', methods: ['POST'])]
    #[Route('/admin/widget/tache/save/{pageWidgetId}', name: 'app_admin_pagewidget_tache_save', methods: ['POST'])]
    public function save(string $pageWidgetId, Request $request, EntityManagerInterface $em, PageWidgetRepository $pageWidgetRepository): JsonResponse
    {
        return $this->saveTache($request, $em, $pageWidgetRepository->find((int) $pageWidgetId));
    }

    private function saveTache(Request $request, EntityManagerInterface $em, $pageWidget): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non connecté'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        if (!empty($data['id'])) {
            $tache = $this->tacheRepository->find((int) $data['id']);
            if (!$tache) {
                return new JsonResponse(['error' => 'Tâche introuvable'], 404);
            }
        } else {
            $tache = new Tache();
            $tache->setCreatedBy($user);
            $tache->setStatus(Tache::STATUS_TODO);
        }

        $tache->setTitle($data['title'] ?? 'Sans titre');
        $tache->setContent($data['content'] ?? null);
        $tache->setStatus($data['status'] ?? $tache->getStatus());

        if (!empty($data['dueDate'])) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i', $data['dueDate']);
            if (!$date) {
                $date = \DateTime::createFromFormat('Y-m-d', $data['dueDate']);
            }
            if ($date) {
                $tache->setDueDate($date);
            }
        }

        if (!empty($data['groupId'])) {
            $group = $this->groupRepository->find((int) $data['groupId']);
            $isMember = $em->getRepository(UserGroup::class)->findOneBy(['user' => $user, 'group' => $group]);
            if (!$group || !$isMember) {
                return new JsonResponse(['error' => 'Vous n\'êtes pas membre de ce groupe'], 403);
            }
            $tache->setGroup($group);
        } else {
            if ($pageWidget) {
                $configFields = $pageWidget->getWidget()->getConfig() ?? [];
                $instanceConfig = $pageWidget->getContent() ?? [];
                $widgetDefaults = $this->widgetConfigService->getDefaults($configFields);
                $config = array_merge($widgetDefaults, $instanceConfig);
                $mode = $config['mode'] ?? 'user';

                if ($mode === 'linked') {
                    $page = $pageWidget->getPage();
                    $pageGroups = $page->getGroups()->toArray();
                    if (!empty($pageGroups)) {
                        $tache->setGroup($pageGroups[0]);
                    }
                }
            }
        }

        if (!empty($data['assignedUserId'])) {
            $assignedUser = $this->userRepository->find((int) $data['assignedUserId']);
            if ($assignedUser && $tache->getGroup()) {
                $isMember = $em->getRepository(UserGroup::class)->findOneBy(['user' => $assignedUser, 'group' => $tache->getGroup()]);
                if (!$isMember) {
                    return new JsonResponse(['error' => 'L\'utilisateur assigné n\'est pas membre du groupe'], 403);
                }
            }
            $tache->setAssignedUser($assignedUser);
        }

        $em->persist($tache);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $tache->getId()]);
    }

    #[Route('/user/widget/tache/group-users/{groupId}', name: 'app_user_pagewidget_tache_group_users')]
    #[Route('/admin/widget/tache/group-users/{groupId}', name: 'app_admin_pagewidget_tache_group_users')]
    public function groupUsers(int $groupId, EntityManagerInterface $em): JsonResponse
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return new JsonResponse([]);
        }

        $members = $em->createQueryBuilder()
            ->select('u.id, u.firstname, u.lastname, u.username, u.pseudo')
            ->from(User::class, 'u')
            ->innerJoin(UserGroup::class, 'ug', 'WITH', 'ug.user = u')
            ->where('ug.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();

        $users = array_map(fn($m) => [
            'id' => $m['id'],
            'name' => $m['pseudo'] ?: ($m['firstname'] . ' ' . $m['lastname']) ?: $m['username'],
        ], $members);

        return new JsonResponse($users);
    }

    #[Route('/user/widget/tache/get/{id}', name: 'app_user_pagewidget_tache_get')]
    #[Route('/admin/widget/tache/get/{id}', name: 'app_admin_pagewidget_tache_get')]
    public function get(int $id): JsonResponse
    {
        $tache = $this->tacheRepository->find($id);
        if (!$tache) {
            return new JsonResponse(['error' => 'Tâche introuvable'], 404);
        }

        return new JsonResponse([
            'id' => $tache->getId(),
            'title' => $tache->getTitle(),
            'content' => $tache->getContent(),
            'status' => $tache->getStatus(),
            'dueDate' => $tache->getDueDate() ? $tache->getDueDate()->format('Y-m-d') : null,
            'groupId' => $tache->getGroup() ? $tache->getGroup()->getId() : null,
            'assignedUserId' => $tache->getAssignedUser() ? $tache->getAssignedUser()->getId() : null,
        ]);
    }

    #[Route('/user/widget/tache/update/{id}', name: 'app_user_pagewidget_tache_update', methods: ['POST'])]
    #[Route('/admin/widget/tache/update/{id}', name: 'app_admin_pagewidget_tache_update', methods: ['POST'])]
    public function update(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tache = $this->tacheRepository->find($id);
        if (!$tache) {
            return new JsonResponse(['error' => 'Tâche introuvable'], 404);
        }

        $tache->cycleStatus();
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $tache->getStatus(),
            'label' => \App\Entity\Tache::STATUSES[$tache->getStatus()],
        ]);
    }

    #[Route('/user/widget/tache/delete/{id}', name: 'app_user_pagewidget_tache_delete', methods: ['POST'])]
    #[Route('/admin/widget/tache/delete/{id}', name: 'app_admin_pagewidget_tache_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tache = $this->tacheRepository->find($id);
        if (!$tache) {
            return new JsonResponse(['error' => 'Tâche introuvable'], 404);
        }

        $em->remove($tache);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    private function loadTaches($user, string $mode, $pageWidget): array
    {
        $qb = $this->tacheRepository->createQueryBuilder('t');

        if ($mode === 'linked') {
            $page = $pageWidget->getPage();
            $pageGroups = $page->getGroups()->toArray();
            if (empty($pageGroups)) {
                return [];
            }
            $groupIds = array_map(fn($g) => $g->getId(), $pageGroups);
            $qb->where('t.group IN (:groupIds)')
               ->setParameter('groupIds', $groupIds);
        } else {
            $qb->where('(t.group IS NULL AND (t.assignedUser = :user OR t.createdBy = :user))')
               ->orWhere('(t.group IS NOT NULL AND t.assignedUser = :user)')
               ->setParameter('user', $user);
        }

        $taches = $qb->getQuery()->getResult();

        usort($taches, function ($a, $b) {
            $statusOrder = ['in_progress' => 0, 'todo' => 1, 'done' => 2];
            $sa = $statusOrder[$a->getStatus()] ?? 3;
            $sb = $statusOrder[$b->getStatus()] ?? 3;
            if ($sa !== $sb) return $sa <=> $sb;

            $da = $a->getDueDate();
            $db = $b->getDueDate();
            if ($da && !$db) return -1;
            if (!$da && $db) return 1;
            if ($da && $db && $da != $db) return $da <=> $db;

            return strcmp($a->getTitle(), $b->getTitle());
        });

        return $taches;
    }
}
