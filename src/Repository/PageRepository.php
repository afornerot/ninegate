<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Page;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function findAccessiblePages(?User $user): array
    {
        $pages = $this->findAll();

        return array_filter($pages, fn ($page) => $this->isPageAccessibleForUser($page, $user));
    }

    public function isPageAccessibleForUser(Page $page, ?User $user): bool
    {
        $userRoles = $user ? $user->getRoles() : ['ROLE_VISITOR'];

        if ($page->getUser() && $page->getUser() === $user) {
            return true;
        }

        if (!empty($page->getRoles())) {
            foreach ($userRoles as $userRole) {
                if (in_array($userRole, $page->getRoles())) {
                    return true;
                }
            }
        }

        if (!$user) {
            return false;
        }

        $pageGroups = $page->getGroups();
        foreach ($pageGroups as $group) {
            $userGroup = $group->getUserGroup($user);
            if ($userGroup) {
                return true;
            }
        }

        return false;
    }

    public function isPageOwnerOrGroupMaster(Page $page, User $user): bool
    {
        if ($page->getUser() && $page->getUser()->getId() === $user->getId()) {
            return true;
        }

        $pageGroups = $page->getGroups();
        foreach ($pageGroups as $group) {
            $userGroup = $group->getUserGroup($user);
            if ($userGroup && UserGroup::ROLE_MASTER === $userGroup->getRole()) {
                return true;
            }
        }

        return false;
    }

    public function canManagePageWidget(Page $page, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($page->getUser() && $page->getUser()->getId() === $user->getId()) {
            return true;
        }

        $pageGroups = $page->getGroups();
        foreach ($pageGroups as $group) {
            $userGroup = $group->getUserGroup($user);
            if (!$userGroup) {
                continue;
            }
            if ($group->getType() === Group::TYPE_WORK_GROUP) {
                if (in_array($userGroup->getRole(), [UserGroup::ROLE_USER, UserGroup::ROLE_MASTER])) {
                    return true;
                }
            } else {
                if (UserGroup::ROLE_MASTER === $userGroup->getRole()) {
                    return true;
                }
            }
        }

        return false;
    }
}
