<?php

namespace App\Voter;

use App\Entity\PageWidget;
use App\Repository\PageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class WidgetVoter extends Voter
{
    public const CAN_MANAGE = 'widget_manage';
    public const CAN_DELETE = 'widget_delete';
    public const CAN_MOVE = 'widget_move';

    public function __construct(
        private RequestStack $requestStack,
        private PageRepository $pageRepository,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::CAN_MANAGE, self::CAN_DELETE, self::CAN_MOVE])
            && $subject instanceof PageWidget;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user) {
            return false;
        }

        $page = $subject->getPage();

        return match ($attribute) {
            self::CAN_MANAGE => $this->canManage($user, $page),
            self::CAN_DELETE => $this->canManage($user, $page),
            self::CAN_MOVE => $this->canManage($user, $page),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

        return str_starts_with($route, 'app_admin');
    }

    private function canManage(object $user, $page): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }

        return $this->pageRepository->canManagePageWidget($page, $user);
    }
}
