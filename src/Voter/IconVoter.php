<?php

namespace App\Voter;

use App\Entity\Icon;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class IconVoter extends Voter
{
    public const EDIT = 'icon_edit';
    public const DELETE = 'icon_delete';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE]) && $subject instanceof Icon;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $icon = $subject;

        return match ($attribute) {
            self::EDIT => $this->canEdit($user, $icon),
            self::DELETE => $this->canDelete($user, $icon),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

        return str_starts_with($route, 'app_admin');
    }

    private function canEdit(User $user, Icon $icon): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $icon->getUser() === $user;
    }

    private function canDelete(User $user, Icon $icon): bool
    {
        if (str_starts_with($icon->getRoute(), 'medias/icon/')) {
            return false;
        }

        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $icon->getUser() === $user;
    }
}
