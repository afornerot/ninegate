<?php

namespace App\Entity;

use App\Repository\UserGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_group', columns: ['user_id', 'group_id'])]
class UserGroup
{
    public const ROLE_MASTER = 'MASTER';
    public const ROLE_USER = 'USER';
    public const ROLE_VIEWER = 'VIEWER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'userGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\Column(length: 50)]
    private string $role = self::ROLE_USER;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, [self::ROLE_MASTER, self::ROLE_USER, self::ROLE_VIEWER])) {
            throw new \InvalidArgumentException('Invalid role');
        }
        $this->role = $role;

        return $this;
    }

    public function isMaster(): bool
    {
        return self::ROLE_MASTER === $this->role;
    }

    public function isViewer(): bool
    {
        return self::ROLE_VIEWER === $this->role;
    }
}
