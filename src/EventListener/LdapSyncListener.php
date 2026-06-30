<?php

namespace App\EventListener;

use App\Entity\Ldap\LdapCapability;
use App\Entity\Ldap\LdapGroup;
use App\Entity\Ldap\LdapUser;
use App\Entity\User;
use App\Entity\Group;
use App\Entity\UserGroup;
use App\Repository\Ldap\LdapGroupRepository;
use App\Repository\Ldap\LdapUserRepository;
use App\Repository\Ldap\LdapCapabilityRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

class LdapSyncListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private LdapGroupRepository $ldapGroupRepo,
        private LdapUserRepository $ldapUserRepo,
        private LdapCapabilityRepository $ldapCapabilityRepo,
    ) {
    }

    #[AsEntityListener(event: Events::postPersist, entity: User::class)]
    #[AsEntityListener(event: Events::postUpdate, entity: User::class)]
    public function syncUserOnPersist(User $user): void
    {
        $this->syncUser($user);
    }

    #[AsEntityListener(event: Events::postRemove, entity: User::class)]
    public function removeUserOnRemove(User $user): void
    {
        $this->removeUser($user);
    }

    #[AsEntityListener(event: Events::postPersist, entity: Group::class)]
    #[AsEntityListener(event: Events::postUpdate, entity: Group::class)]
    public function syncGroupOnPersist(Group $group): void
    {
        $this->syncGroup($group);
    }

    #[AsEntityListener(event: Events::postRemove, entity: Group::class)]
    public function removeGroupOnRemove(Group $group): void
    {
        $this->removeGroup($group);
    }

    #[AsEntityListener(event: Events::postPersist, entity: UserGroup::class)]
    #[AsEntityListener(event: Events::postUpdate, entity: UserGroup::class)]
    #[AsEntityListener(event: Events::postRemove, entity: UserGroup::class)]
    public function syncUserOnUserGroupChange(UserGroup $userGroup): void
    {
        $this->syncUser($userGroup->getUser());
    }

    private function syncUser(User $user): void
    {
        $primaryGroupGid = 1000;
        $otherGroupIds = [];
        foreach ($user->getUserGroups() as $ug) {
            $gid = $ug->getGroup()->getId() + 1000;
            if ($ug->getRole() === UserGroup::ROLE_MASTER && $primaryGroupGid === 1000) {
                $primaryGroupGid = $gid;
            } else {
                $otherGroupIds[] = $gid;
            }
        }
        if ($primaryGroupGid === 1000 && !empty($otherGroupIds)) {
            $primaryGroupGid = array_shift($otherGroupIds);
        }
        $otherGroupIds = array_values(array_diff($otherGroupIds, [$primaryGroupGid]));

        $ldapUser = $this->ldapUserRepo->findOneBy(['uidnumber' => $user->getId() + 1000]);
        if (!$ldapUser) {
            $ldapUser = new LdapUser();
        }

        $ldapUser->setName($user->getUsername());
        $ldapUser->setUidnumber($user->getId() + 1000);
        $ldapUser->setPrimarygroup($primaryGroupGid);
        $ldapUser->setOthergroups(!empty($otherGroupIds) ? implode(',', $otherGroupIds) : '');
        $ldapUser->setGivenname($user->getFirstname() ?? '');
        $ldapUser->setSn($user->getLastname() ?? '');
        $ldapUser->setMail($user->getEmail() ?? '');
        $ldapUser->setPassbcrypt(bin2hex($user->getPassword()));
        $ldapUser->setDisabled(0);
        $ldapUser->setSshkeys('');

        $this->em->persist($ldapUser);
        $this->em->flush();
    }

    private function removeUser(User $user): void
    {
        $ldapUser = $this->ldapUserRepo->findOneBy(['uidnumber' => $user->getId() + 1000]);
        if ($ldapUser) {
            $this->em->remove($ldapUser);
            $this->em->flush();
        }
    }

    private function syncGroup(Group $group): void
    {
        $ldapGroup = $this->ldapGroupRepo->findOneBy(['gidnumber' => $group->getId() + 1000]);
        if (!$ldapGroup) {
            $ldapGroup = new LdapGroup();
        }

        $ldapGroup->setName($group->getName());
        $ldapGroup->setGidnumber($group->getId() + 1000);

        $this->em->persist($ldapGroup);
        $this->em->flush();
    }

    private function removeGroup(Group $group): void
    {
        $ldapGroup = $this->ldapGroupRepo->findOneBy(['gidnumber' => $group->getId() + 1000]);
        if ($ldapGroup) {
            $this->em->remove($ldapGroup);
            $this->em->flush();
        }
    }

    private function syncCapabilities(User $user): void
    {
        // Remove existing capabilities for this user
        $existingCaps = $this->ldapCapabilityRepo->findBy(['userid' => $user->getId() + 1000]);
        foreach ($existingCaps as $cap) {
            $this->em->remove($cap);
        }

        $uidnumber = $user->getId() + 1000;

        foreach ($user->getUserGroups() as $ug) {
            $group = $ug->getGroup();
            $ouName = strtolower('ou=' . $group->getName() . ',dc=ninegate,dc=local');

            if (in_array($ug->getRole(), [UserGroup::ROLE_MASTER, UserGroup::ROLE_USER])) {
                $cap = new LdapCapability();
                $cap->setUserid($uidnumber);
                $cap->setAction('search');
                $cap->setObject($ouName);
                $this->em->persist($cap);
            }

            if ($ug->getRole() === UserGroup::ROLE_MASTER) {
                $cap = new LdapCapability();
                $cap->setUserid($uidnumber);
                $cap->setAction('add');
                $cap->setObject($ouName);
                $this->em->persist($cap);

                $cap = new LdapCapability();
                $cap->setUserid($uidnumber);
                $cap->setAction('modify');
                $cap->setObject($ouName);
                $this->em->persist($cap);
            }
        }

        $this->em->flush();
    }
}
