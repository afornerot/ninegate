<?php

namespace App\Security;

use App\Entity\Group;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogRepository;
use App\Repository\PageWidgetRepository;
use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractFileVoter
{
    public function __construct(
        private PageWidgetRepository $pageWidgetRepository,
        private BlogArticleRepository $blogArticleRepository,
        private BlogRepository $blogRepository,
    ) {
    }

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    private function canManage(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        return match ($domain) {
            'pagewidget' => $this->canManagePageWidget((int) $id, $token),
            'blog' => $this->canManageBlog((int) $id, $token),
            default => false,
        };
    }

    private function canManagePageWidget(int $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        $pageWidget = $this->pageWidgetRepository->find($id);
        if (!$pageWidget) {
            return false;
        }

        $page = $pageWidget->getPage();
        if (!$page) {
            return false;
        }

        if ($page->getUser() && $page->getUser()->getId() === $user->getId()) {
            return true;
        }

        $pageGroups = $page->getGroups();
        foreach ($pageGroups as $group) {
            if ($group->getType() !== Group::TYPE_WORK_GROUP) {
                continue;
            }
            $userGroup = $group->getUserGroup($user);
            if ($userGroup && in_array($userGroup->getRole(), [\App\Entity\UserGroup::ROLE_USER, \App\Entity\UserGroup::ROLE_MASTER])) {
                return true;
            }
        }

        return false;
    }

    private function canManageBlog(int $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        $blog = $this->blogRepository->find($id);
        if (!$blog) {
            return false;
        }

        return $this->blogRepository->isBlogAccessibleForUser($blog, $user);
    }
}
