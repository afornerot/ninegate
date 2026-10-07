<?php

namespace App\Security;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogRepository;
use App\Repository\PageRepository;
use App\Repository\PageWidgetRepository;
use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractFileVoter
{
    private const PUBLIC_DOMAINS = ['avatar', 'logo', 'icon'];

    public function __construct(
        private PageWidgetRepository $pageWidgetRepository,
        private BlogArticleRepository $blogArticleRepository,
        private BlogRepository $blogRepository,
        private PageRepository $pageRepository,
    ) {
    }

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        if (in_array($domain, self::PUBLIC_DOMAINS)) {
            return true;
        }

        return match ($domain) {
            'pagewidgetfile' => $this->canViewPageWidgetFile((int) $id, $user),
            'pagewidget' => $this->canViewPageWidgetFile((int) $id, $user),
            'blog' => $this->canViewBlog((int) $id, $user),
            'blogarticle' => $this->canViewBlogArticle((int) $id, $user),
            default => false,
        };
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        if (in_array($domain, self::PUBLIC_DOMAINS)) {
            return true;
        }

        return $this->canManage($domain, $id, $token);
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        if (in_array($domain, self::PUBLIC_DOMAINS)) {
            return true;
        }

        return $this->canManage($domain, $id, $token);
    }

    private function canViewPageWidgetFile(int $id, ?User $user): bool
    {
        $pageWidget = $this->pageWidgetRepository->find($id);
        if (!$pageWidget || !$pageWidget->getPage()) {
            return false;
        }

        return $this->pageRepository->isPageAccessibleForUser($pageWidget->getPage(), $user);
    }

    private function canViewBlog(int $id, ?User $user): bool
    {
        $blog = $this->blogRepository->find($id);
        if (!$blog) {
            return false;
        }

        return $this->blogRepository->isBlogAccessibleForUser($blog, $user);
    }

    private function canViewBlogArticle(int $id, ?User $user): bool
    {
        $article = $this->blogArticleRepository->find($id);
        if (!$article || !$article->getBlog()) {
            return false;
        }

        return $this->blogRepository->isBlogAccessibleForUser($article->getBlog(), $user);
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
            'pagewidgetfile' => $this->canManagePageWidget((int) $id, $token),
            'pagewidget' => $this->canManagePageWidget((int) $id, $token),
            'blogarticle' => $this->canManageBlogArticle((int) $id, $token),
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

    private function canManageBlogArticle(int $id, TokenInterface $token): bool
    {
        $user = $token->getUser();

        $article = $this->blogArticleRepository->find($id);
        if (!$article) {
            return false;
        }

        $blog = $article->getBlog();
        if (!$blog) {
            return false;
        }

        return $this->blogRepository->isBlogAccessibleForUser($blog, $user);
    }
}