<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
class Group
{
    public const TYPE_ORGANISATION = 'Organisation';
    public const TYPE_WORK_GROUP = 'Groupe de Travail';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column]
    private bool $isOpen = true;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_WORK_GROUP;

    #[ORM\OneToMany(targetEntity: UserGroup::class, mappedBy: 'group', cascade: ['remove', 'persist'])]
    private Collection $userGroups;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'groups')]
    #[ORM\JoinTable(name: 'group_user')]
    private Collection $users;

    public function __construct()
    {
        $this->userGroups = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo ? $this->logo : 'medias/icon/icon_users.png';
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getUserGroups(): Collection
    {
        return $this->userGroups;
    }

    public function getUserGroup(User $user): ?UserGroup
    {
        foreach ($this->userGroups as $ug) {
            if ($ug->getUser() === $user) {
                return $ug;
            }
        }

        return null;
    }

    public function addUserGroup(UserGroup $userGroup): static
    {
        if (!$this->userGroups->contains($userGroup)) {
            $this->userGroups->add($userGroup);
            $userGroup->setGroup($this);
        }

        return $this;
    }

    public function removeUserGroup(UserGroup $userGroup): static
    {
        if ($this->userGroups->contains($userGroup)) {
            $this->userGroups->removeElement($userGroup);
        }

        return $this;
    }

    public function addUser(User $user, string $role = UserGroup::ROLE_USER): static
    {
        $userGroup = new UserGroup();
        $userGroup->setUser($user);
        $userGroup->setGroup($this);
        $userGroup->setRole($role);
        $this->addUserGroup($userGroup);

        return $this;
    }

    public function removeUser(User $user): static
    {
        $userGroup = $this->getUserGroup($user);
        if ($userGroup) {
            $this->userGroups->removeElement($userGroup);
        }

        return $this;
    }
}
