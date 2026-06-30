<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\UserGroup;
use App\Form\GroupType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    private const GROUP_PREFIX = '/group';
    private const ROUTE_PREFIX_USER = 'app_user_group';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_group';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user'.self::GROUP_PREFIX, name: 'app_user_group_list')]
    #[Route('/admin'.self::GROUP_PREFIX, name: 'app_admin_group_list')]
    public function list(?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $routePrefix = $isAdmin ? self::ROUTE_PREFIX_ADMIN : self::ROUTE_PREFIX_USER;

        if ($isAdmin) {
            $groups = $this->groupRepository->findAll();
        } else {
            $user = $this->getUser();
            $groups = $this->userRepository->findGroupsByUser($user);
        }

        return $this->render('group/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $isAdmin ? 'Liste des Groupes' : 'Mes Groupes',
            'groups' => $groups,
            'admin' => $isAdmin,
            'routesubmit' => $routePrefix.'_submit',
            'routeupdate' => $routePrefix.'_update',
            'routeusergroup' => $isAdmin ? 'app_admin_usergroup_update' : 'app_user_usergroup_update',
            'routedelete' => $routePrefix.'_delete',
            'routesubscribe' => $isAdmin ? null : 'app_user_group_subscribe',
            'routeleave' => $isAdmin ? null : 'app_user_group_leave',
        ]);
    }

    #[Route('/user'.self::GROUP_PREFIX.'/subscribe', name: 'app_user_group_subscribe')]
    public function subscribe(): Response
    {
        $user = $this->getUser();
        $userGroups = $this->userRepository->findGroupsByUser($user);
        $userGroupIds = array_map(fn ($g) => $g->getId(), $userGroups);

        $availableGroups = $this->groupRepository->findOpenGroupsNotInList($userGroupIds);

        return $this->render('group/subscribe.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Groupes Disponibles',
            'groups' => $availableGroups,
            'routecancel' => 'app_user_group_list',
            'routejoin' => 'app_user_group_join',
        ]);
    }

    #[Route('/user'.self::GROUP_PREFIX.'/join/{id}', name: 'app_user_group_join')]
    public function join(int $id): Response
    {
        $user = $this->getUser();
        $group = $this->groupRepository->find($id);

        if (!$this->isGranted(GroupVoter::SUBSCRIBE, $group)) {
            $this->addFlash('error', 'Vous ne pouvez pas rejoindre ce groupe.');

            return $this->redirectToRoute('app_user_group_subscribe');
        }

        $userGroup = $group->getUserGroup($user);
        if ($userGroup) {
            return $this->redirectToRoute('app_user_group_subscribe');
        }

        $group->addUser($user, UserGroup::ROLE_VIEWER);
        $this->em->flush();

        $this->addFlash('success', 'Vous avez rejoint le groupe '.$group->getName());

        return $this->redirectToRoute('app_user_group_list');
    }

    #[Route('/user'.self::GROUP_PREFIX.'/leave/{id}', name: 'app_user_group_leave')]
    public function leave(int $id): Response
    {
        $user = $this->getUser();
        $group = $this->groupRepository->find($id);

        if (!$group) {
            return $this->redirectToRoute('app_user_group_list');
        }

        if (!$this->isGranted(GroupVoter::LEAVE, $group)) {
            $this->addFlash('error', 'Vous ne pouvez pas quitter ce groupe.');

            return $this->redirectToRoute('app_user_group_list');
        }

        $userGroup = $group->getUserGroup($user);
        $this->em->remove($userGroup);
        $this->em->flush();

        $this->addFlash('success', 'Vous avez quitté le groupe '.$group->getName());

        return $this->redirectToRoute('app_user_group_list');
    }

    #[Route('/user'.self::GROUP_PREFIX.'/submit', name: 'app_user_group_submit')]
    #[Route('/admin'.self::GROUP_PREFIX.'/submit', name: 'app_admin_group_submit')]
    public function submit(Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $group = new Group();
        if (!$isAdmin && !$this->isGranted(GroupVoter::CREATE, $group)) {
            return $this->redirectToRoute($listRoute);
        }

        $form = $this->createForm(GroupType::class, $group, ['mode' => 'submit', 'isAdmin' => $isAdmin]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($group);
            $this->em->flush();
            if (!$isAdmin) {
                $userGroup = new UserGroup();
                $userGroup->setUser($this->getUser());
                $userGroup->setGroup($group);
                $userGroup->setRole(UserGroup::ROLE_MASTER);
                $this->em->persist($userGroup);
                $this->em->flush();
            }

            $userGroupRoute = $isAdmin ? 'app_admin_usergroup_update' : 'app_user_usergroup_update';

            return $this->redirectToRoute($userGroupRoute, ['id' => $group->getId()]);
        }

        return $this->render('group/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Groupe',
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_group_delete' : 'app_user_group_delete',
            'group' => $group,
        ]);
    }

    #[Route('/user'.self::GROUP_PREFIX.'/update/{id}', name: 'app_user_group_update')]
    #[Route('/admin'.self::GROUP_PREFIX.'/update/{id}', name: 'app_admin_group_update')]
    public function update(int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $group = $this->em->getRepository(Group::class)->find($id);
        if (!$group) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$isAdmin && !$this->isGranted(GroupVoter::EDIT, $group)) {
            return $this->redirectToRoute($listRoute);
        }

        $form = $this->createForm(GroupType::class, $group, ['mode' => 'update', 'isAdmin' => $isAdmin]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('group/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Groupe = '.$group->getName(),
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_group_delete' : 'app_user_group_delete',
            'group' => $group,
        ]);
    }

    #[Route('/user'.self::GROUP_PREFIX.'/delete/{id}', name: 'app_user_group_delete')]
    #[Route('/admin'.self::GROUP_PREFIX.'/delete/{id}', name: 'app_admin_group_delete')]
    public function delete(int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);
        $routePrefix = $isAdmin ? self::ROUTE_PREFIX_ADMIN : self::ROUTE_PREFIX_USER;

        $group = $this->groupRepository->find($id);
        if (!$group) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$isAdmin && !$this->isGranted(GroupVoter::DELETE, $group)) {
            return $this->redirectToRoute($listRoute);
        }

        try {
            $this->em->remove($group);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute($routePrefix.'_update', ['id' => $id]);
        }

        return $this->redirectToRoute($listRoute);
    }
}
