<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Voter\GroupVoter;
use App\Voter\UserGroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserGroupController extends AbstractController
{
    private const USERGROUP_PREFIX = '/usergroup';
    private const ROUTE_PREFIX_USER = 'app_user_usergroup';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_usergroup';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserGroupRepository $userGroupRepository,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? 'app_admin_group_list' : 'app_user_group_list';
    }

    #[Route('/user'.self::USERGROUP_PREFIX.'/update/{id}', name: 'app_user_usergroup_update')]
    #[Route('/admin'.self::USERGROUP_PREFIX.'/update/{id}', name: 'app_admin_usergroup_update')]
    #[IsGranted(GroupVoter::EDIT, 'group')]
    public function update(Group $group, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $id = $group->getId();
        if (!$group) {
            return $this->redirectToRoute($this->getListRoute($isAdmin));
        }

        $userGroups = $this->userGroupRepository->createQueryBuilder('ug')
            ->join('ug.user', 'u')
            ->where('ug.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();

        $usersInGroup = array_map(fn (UserGroup $ug) => $ug->getUser(), $userGroups);
        $allUsers = $this->userRepository->findAll();

        $usersInGroupIds = array_map(fn (User $u) => $u->getId(), $usersInGroup);
        $usersNotInGroup = array_filter($allUsers, fn (User $user) => !in_array($user->getId(), $usersInGroupIds));

        $userId = $request->query->get('userId');
        $action = $request->query->get('action');

        if ($userId && $action) {
            $thisUser = $this->getUser();
            $user = $this->em->getRepository(User::class)->find($userId);
            if ($user) {
                if ('add' === $action) {
                    $group->addUser($user, UserGroup::ROLE_USER);
                } elseif ('remove' === $action) {
                    if ($thisUser instanceof User && $user->getId() === $thisUser->getId()) {
                        $this->addFlash('danger', 'Vous ne pouvez pas vous retirer du groupe.');
                        return $this->redirectToRoute($_route, ['id' => $id]);
                    }

                    $targetUserGroup = $this->userGroupRepository->findOneBy([
                        'user' => $user,
                        'group' => $group,
                    ]);

                    if ($targetUserGroup && !$this->isGranted(UserGroupVoter::REMOVE, $targetUserGroup)) {
                        $this->addFlash('danger', 'Vous ne pouvez pas retirer cet utilisateur du groupe.');
                        return $this->redirectToRoute($_route, ['id' => $id]);
                    }

                    if ($targetUserGroup) {
                        $this->em->remove($targetUserGroup);
                    }
                } elseif ('setrole' === $action) {
                    if (!$isAdmin && $thisUser instanceof User && $user->getId() === $thisUser->getId()) {
                        $this->addFlash('danger', 'Vous ne pouvez pas changer votre propre rôle.');
                        return $this->redirectToRoute($_route, ['id' => $id]);
                    }

                    $targetUserGroup = $this->userGroupRepository->findOneBy([
                        'user' => $user,
                        'group' => $group,
                    ]);

                    if ($targetUserGroup && !$this->isGranted(UserGroupVoter::CHANGE_ROLE, $targetUserGroup)) {
                        $this->addFlash('danger', 'Vous ne pouvez pas modifier le rôle de cet utilisateur.');
                        return $this->redirectToRoute($_route, ['id' => $id]);
                    }

                    $newRole = $request->query->get('role');
                    $userGroup = $this->userGroupRepository->findOneBy([
                        'user' => $user,
                        'group' => $group,
                    ]);

                    if ($userGroup && in_array($newRole, [UserGroup::ROLE_USER, UserGroup::ROLE_VIEWER, UserGroup::ROLE_MASTER])) {
                        $userGroup->setRole($newRole);
                    }
                }
                $this->em->flush();

                return $this->redirectToRoute($_route, ['id' => $id]);
            }
        }

        return $this->render('usergroup/update.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => 'Gestion des utilisateurs - '.$group->getName(),
            'group' => $group,
            'userGroups' => $userGroups,
            'usersNotInGroup' => $usersNotInGroup,
            'routecancel' => $this->getListRoute($isAdmin),
            'routeupdate' => $isAdmin ? 'app_admin_usergroup_update' : 'app_user_usergroup_update',
        ]);
    }
}