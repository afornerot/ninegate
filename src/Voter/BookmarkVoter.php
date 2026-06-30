<?php

namespace App\Voter;

use App\Entity\Bookmark;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BookmarkVoter extends Voter
{
    public const EDIT = 'bookmark_edit';
    public const DELETE = 'bookmark_delete';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Bookmark;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Owner can always manage their bookmarks
        if ($subject->getUser() && $subject->getUser()->getId() === $user->getId()) {
            return true;
        }

        // Admin can manage all bookmarks
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        return false;
    }
}
