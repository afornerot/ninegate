<?php

namespace App\Voter;

use App\Entity\Blog;
use App\Entity\BlogArticle;
use App\Entity\User;
use App\Repository\BlogRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BlogVoter extends Voter
{
    public const VIEW = 'blog_view';
    public const CREATE = 'blog_create';
    public const EDIT = 'blog_edit';
    public const DELETE = 'blog_delete';
    public const ARTICLE_EDIT = 'blogarticle_edit';
    public const ARTICLE_CREATE = 'blogarticle_create';

    public function __construct(
        private RequestStack $requestStack,
        private BlogRepository $blogRepository,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::ARTICLE_EDIT, self::ARTICLE_CREATE])) {
            return false;
        }
        if (in_array($attribute, [self::CREATE, self::ARTICLE_CREATE])) {
            return true;
        }
        return $subject instanceof Blog || $subject instanceof BlogArticle;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (self::VIEW === $attribute) {
            return $this->canView($user, $subject);
        }

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($user, $subject),
            self::DELETE => $this->canDelete($user, $subject),
            self::CREATE => true,
            self::ARTICLE_EDIT => $this->canEditArticle($user, $subject),
            self::ARTICLE_CREATE => $this->canCreateArticle($user, $subject instanceof BlogArticle ? $subject->getBlog() : $subject),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';
        return str_starts_with($route, 'app_admin');
    }

    private function canView(?User $user, ?Blog $blog = null): bool
    {
        if ($user && $user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        if (!$blog || !$user) {
            return false;
        }
        return $this->blogRepository->isBlogAccessibleForUser($blog, $user);
    }

    private function canEdit(User $user, Blog $blog): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        return $this->blogRepository->isBlogOwnerOrGroupMaster($blog, $user);
    }

    private function canDelete(User $user, Blog $blog): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        return $this->blogRepository->isBlogOwnerOrGroupMaster($blog, $user);
    }

    private function canEditArticle(User $user, BlogArticle $article): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        $blog = $article->getBlog();
        if (!$blog) {
            return false;
        }

        if ($this->blogRepository->isBlogOwnerOrGroupMaster($blog, $user)) {
            return true;
        }

        return $article->getUser() && $article->getUser()->getId() === $user->getId();
    }

    private function canCreateArticle(User $user, Blog $blog): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $this->blogRepository->isBlogUserOrAbove($blog, $user);
    }
}
