<?php

namespace App\Voter;

use App\Entity\User;
use App\Entity\UserGroup;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserGroupVoter extends Voter
{
    public const REMOVE = 'usergroup_remove';
    public const CHANGE_ROLE = 'usergroup_change_role';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::REMOVE, self::CHANGE_ROLE]) && $subject instanceof UserGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $userGroup = $subject;
        $group = $userGroup->getGroup();

        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        $currentUserGroup = $group->getUserGroup($user);
        if (!$currentUserGroup || UserGroup::ROLE_MASTER !== $currentUserGroup->getRole()) {
            return false;
        }

        if ($user === $userGroup->getUser()) {
            return false;
        }

        return true;
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

        return str_starts_with($route, 'app_admin');
    }
}
