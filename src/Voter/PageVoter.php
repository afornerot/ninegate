<?php

namespace App\Voter;

use App\Entity\Page;
use App\Entity\User;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PageVoter extends Voter
{
    public const VIEW = 'page_view';
    public const CREATE = 'page_create';
    public const EDIT = 'page_edit';
    public const DELETE = 'page_delete';

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private PageRepository $pageRepository,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        return $subject instanceof Page;
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

        $page = $subject;

        return match ($attribute) {
            self::EDIT => $this->canEdit($user, $page),
            self::DELETE => $this->canDelete($user, $page),
            self::CREATE => $this->canCreate($user),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

        return str_starts_with($route, 'app_admin');
    }

    private function canView(?User $user, ?Page $page = null): bool
    {
        if ($user && $user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        if (!$page) {
            return false;
        }

        return $this->pageRepository->isPageAccessibleForUser($page, $user);
    }

    private function canEdit(User $user, Page $page): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $this->pageRepository->isPageOwnerOrGroupMaster($page, $user);
    }

    private function canDelete(User $user, Page $page): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $this->pageRepository->isPageOwnerOrGroupMaster($page, $user);
    }

    private function canCreate(User $user): bool
    {
        return true;
    }
}
