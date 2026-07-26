<?php

namespace App\Voter;

use App\Entity\Calendar;
use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Repository\CalendarRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CalendarVoter extends Voter
{
    public const VIEW = 'calendar_view';
    public const CREATE = 'calendar_create';
    public const EDIT = 'calendar_edit';
    public const DELETE = 'calendar_delete';
    public const EVENT_EDIT = 'calendarevent_edit';
    public const EVENT_CREATE = 'calendarevent_create';

    public function __construct(
        private CalendarRepository $calendarRepository,
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::EVENT_EDIT, self::EVENT_CREATE])) {
            return false;
        }
        if (in_array($attribute, [self::CREATE, self::EVENT_CREATE])) {
            return true;
        }
        return $subject instanceof Calendar || $subject instanceof CalendarEvent;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($user, $subject),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($user, $subject),
            self::DELETE => $this->canDelete($user, $subject),
            self::EVENT_EDIT => $this->canEditEvent($user, $subject),
            self::EVENT_CREATE => $this->canCreateEvent($user, $subject),
            default => false,
        };
    }

    private function isAdminRoute(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';
        return str_starts_with($route, 'app_admin');
    }

    private function canView(User $user, $subject): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        if ($subject instanceof Calendar) {
            return $this->calendarRepository->isCalendarAccessibleForUser($subject, $user);
        }
        if ($subject instanceof CalendarEvent) {
            return $this->calendarRepository->isCalendarAccessibleForUser($subject->getCalendar(), $user);
        }
        return false;
    }

    private function canCreate(User $user): bool
    {
        return $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_MASTER');
    }

    private function canEdit(User $user, $subject): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        if ($subject instanceof Calendar) {
            return $this->calendarRepository->isCalendarOwnerOrGroupMaster($subject, $user);
        }
        return false;
    }

    private function canDelete(User $user, $subject): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        if ($subject instanceof Calendar) {
            return $this->calendarRepository->isCalendarOwnerOrGroupMaster($subject, $user);
        }
        return false;
    }

    private function canEditEvent(User $user, $subject): bool
    {
        if ($user->hasRole('ROLE_ADMIN') && $this->isAdminRoute()) {
            return true;
        }
        if ($subject instanceof CalendarEvent) {
            if ($subject->getUser()->getId() === $user->getId()) {
                return true;
            }
            return $this->calendarRepository->isCalendarOwnerOrGroupMaster($subject->getCalendar(), $user);
        }
        return false;
    }

    private function canCreateEvent(User $user, $subject): bool
    {
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }
        if ($subject instanceof Calendar) {
            return $this->calendarRepository->isCalendarUserOrAbove($subject, $user);
        }
        return false;
    }
}
