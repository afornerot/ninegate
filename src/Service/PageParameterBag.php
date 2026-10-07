<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\BlogRepository;
use App\Repository\CalendarRepository;
use App\Repository\PageRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag as SymfonyParameterBag;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PageParameterBag extends SymfonyParameterBag
{
    public function __construct(
        private PageRepository $pageRepository,
        private TokenStorageInterface $tokenStorage,
        private BlogRepository $blogRepository,
        private CalendarRepository $calendarRepository,
    ) {
        $this->add([
            'group_orga_role' => [],
            'personal' => [],
            'work_group' => [],
            'has_pages' => false,
            'has_blogs' => false,
            'has_calendars' => false,
        ]);
    }

    public function load(): void
    {
        $user = $this->getUser();
        $userRoles = $user ? $user->getRoles() : ['ROLE_VISITOR'];

        $allPages = $this->pageRepository->findAccessiblePages($user);
        $personalPages = [];
        $groupOrgaRolePages = [];
        $workGroupPages = [];

        foreach ($allPages as $page) {
            if ($user && $page->getUser() && $page->getUser()->getId() === $user->getId()) {
                $personalPages[] = $page;
                continue;
            }

            $isWorkGroupMaster = false;
            $isOtherGroup = false;

            if ($user) {
                foreach ($page->getGroups() as $group) {
                    $userGroup = $group->getUserGroup($user);
                    if ($userGroup) {
                        if (Group::TYPE_WORK_GROUP === $group->getType()) {
                            $isWorkGroupMaster = true;
                        } else {
                            $isOtherGroup = true;
                        }
                    }
                }
            }

            $hasMatchingRole = false;
            if (!empty($page->getRoles())) {
                foreach ($userRoles as $userRole) {
                    if (in_array($userRole, $page->getRoles())) {
                        $hasMatchingRole = true;
                        break;
                    }
                }
            }

            if ($isWorkGroupMaster) {
                $workGroupPages[] = $page;
            } elseif ($isOtherGroup || $hasMatchingRole) {
                $groupOrgaRolePages[] = $page;
            }
        }

        usort($personalPages, fn ($a, $b) => $a->getPageOrder() <=> $b->getPageOrder());
        usort($groupOrgaRolePages, fn ($a, $b) => $a->getPageOrder() <=> $b->getPageOrder());
        usort($workGroupPages, fn ($a, $b) => $a->getPageOrder() <=> $b->getPageOrder());

        $accessibleBlogs = $this->blogRepository->findAccessibleBlogs($user);
        $accessibleCalendars = $this->calendarRepository->findAccessibleCalendars($user);

        $hasPages = count($personalPages) > 0
            || count($groupOrgaRolePages) > 0
            || count($workGroupPages) > 0;
        $hasBlogs = count($accessibleBlogs) > 0;
        $hasCalendars = count($accessibleCalendars) > 0;

        $this->clear();
        $this->add([
            'personal' => $personalPages,
            'group_orga_role' => $groupOrgaRolePages,
            'work_group' => $workGroupPages,
            'has_pages' => $hasPages,
            'has_blogs' => $hasBlogs,
            'has_calendars' => $hasCalendars,
        ]);
    }

    public function reload(): void
    {
        $this->load();
    }

    private function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }
}