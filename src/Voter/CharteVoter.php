<?php

namespace App\Voter;

use App\Entity\Charte;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CharteVoter extends Voter
{
    public const MANAGE = 'charte_manage';
    public const VIEW = 'charte_view';
    public const SIGN = 'charte_sign';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::MANAGE, self::VIEW, self::SIGN])) {
            return false;
        }
        if (self::MANAGE === $attribute) {
            return true;
        }
        return $subject instanceof Charte;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE => $this->canManage($user),
            self::VIEW => $this->canView($user, $subject),
            self::SIGN => $this->canSign($user, $subject),
            default => false,
        };
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('ROLE_ADMIN') && $this->isAdminRoute();
    }

    private function canView(User $user, Charte $charte): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        return $charte->isAccessibleToUser($user);
    }

    private function canSign(User $user, Charte $charte): bool
    {
        if (!$this->canView($user, $charte)) {
            return false;
        }
        if (!$charte->isRequireSignature()) {
            return false;
        }
        return !$charte->hasUserSigned($user);
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';
        return str_starts_with($route, 'app_admin');
    }
}
