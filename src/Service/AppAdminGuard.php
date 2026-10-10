<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppAdminGuard
{
    private ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function getAdminUsername(): string
    {
        return (string) $this->parameterBag->get('appAdmin');
    }

    public function isAdminUsername(string $username): bool
    {
        return $username === $this->getAdminUsername();
    }

    public function isAdminUser(?User $user): bool
    {
        return null !== $user && $this->isAdminUsername((string) $user->getUsername());
    }

    public function isAdminGroup(?Group $group): bool
    {
        return null !== $group && 'all' === $group->getSlug();
    }

    public function assertNotAdminUser(?User $user, string $action): void
    {
        if ($this->isAdminUser($user)) {
            throw new \RuntimeException(sprintf(
                'APP_ADMIN est intouchable : %s interdite.',
                $action
            ));
        }
    }

    public function assertNotAdminUsername(string $username, string $context): void
    {
        if ($this->isAdminUsername($username)) {
            throw new \RuntimeException(sprintf(
                'Le username "%s" est réservé à APP_ADMIN (contexte : %s).',
                $this->getAdminUsername(),
                $context
            ));
        }
    }
}