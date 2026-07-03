<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\UserGroup;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostFlushEventArgs;

class UserAllListener
{
    private bool $needsFlush = false;

    public function __construct(
        private EntityManagerInterface $em,
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->needsFlush) {
            return;
        }

        $this->needsFlush = false;

        $all = $this->groupRepository->findOneBy(['slug' => 'all']);
        if (!$all) {
            return;
        }

        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $alreadyIn = false;
            foreach ($user->getUserGroups() as $ug) {
                if ($ug->getGroup()->getId() === $all->getId()) {
                    $alreadyIn = true;
                    break;
                }
            }

            if (!$alreadyIn) {
                $userGroup = new UserGroup();
                $userGroup->setUser($user);
                $userGroup->setGroup($all);
                $userGroup->setRole(UserGroup::ROLE_USER);
                $this->em->persist($userGroup);
            }
        }

        $this->em->flush();
    }

    #[AsEntityListener(event: Events::postPersist, entity: User::class)]
    #[AsEntityListener(event: Events::postUpdate, entity: User::class)]
    public function ensureAllGroup(User $user): void
    {
        $this->needsFlush = true;
    }

    #[AsEntityListener(event: Events::preRemove, entity: UserGroup::class)]
    public function preventRemoveFromAll(UserGroup $userGroup): void
    {
        if ($userGroup->getGroup()->getSlug() === 'all') {
            throw new \RuntimeException("Vous ne pouvez pas quitter le groupe 'Tous'.");
        }
    }
}
