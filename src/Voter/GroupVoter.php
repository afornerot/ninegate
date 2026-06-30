<?php

namespace App\Voter;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GroupVoter extends Voter
{
    public const VIEW = 'group_view';
    public const CREATE = 'group_create';
    public const EDIT = 'group_edit';
    public const DELETE = 'group_delete';
    public const SUBSCRIBE = 'group_subscribe';
    public const LEAVE = 'group_leave';

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::SUBSCRIBE, self::LEAVE])) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        return $subject instanceof Group;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $group = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($user, $group),
            self::EDIT => $this->canEdit($user, $group),
            self::DELETE => $this->canDelete($user, $group),
            self::CREATE => $this->canCreate($user),
            self::SUBSCRIBE => $this->canSubscribe($user, $group),
            self::LEAVE => $this->canLeave($user, $group),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

        return str_starts_with($route, 'app_admin');
    }

    private function canView(User $user, ?Group $group = null): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        if (!$group) {
            return false;
        }

        $userGroup = $this->em->getRepository(UserGroup::class)->findOneBy([
            'user' => $user,
            'group' => $group,
        ]);

        return null !== $userGroup;
    }

    private function canEdit(User $user, Group $group): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        $userGroup = $this->em->getRepository(UserGroup::class)->findOneBy([
            'user' => $user,
            'group' => $group,
        ]);

        return $userGroup && UserGroup::ROLE_MASTER === $userGroup->getRole();
    }

    private function canDelete(User $user, Group $group): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        $userGroup = $this->em->getRepository(UserGroup::class)->findOneBy([
            'user' => $user,
            'group' => $group,
        ]);

        return $userGroup && UserGroup::ROLE_MASTER === $userGroup->getRole();
    }

    private function canCreate(User $user): bool
    {
        return $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_MASTER');
    }

    private function canSubscribe(User $user, Group $group): bool
    {
        if (!$group || !$group->isOpen()) {
            return false;
        }

        $userGroup = $this->em->getRepository(UserGroup::class)->findOneBy([
            'user' => $user,
            'group' => $group,
        ]);

        return !$userGroup;
    }

    private function canLeave(User $user, Group $group): bool
    {
        if (!$group || !$group->isOpen()) {
            return false;
        }

        $userGroup = $this->em->getRepository(UserGroup::class)->findOneBy([
            'user' => $user,
            'group' => $group,
        ]);

        return $userGroup && UserGroup::ROLE_MASTER !== $userGroup->getRole();
    }
}
