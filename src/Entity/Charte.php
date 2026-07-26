<?php

namespace App\Entity;

use App\Repository\CharteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharteRepository::class)]
class Charte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column]
    private bool $requireSignature = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'charte_group')]
    private Collection $groups;

    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $roles = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(targetEntity: CharteSignature::class, mappedBy: 'charte', cascade: ['remove'], orphanRemoval: true)]
    private Collection $signatures;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->signatures = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function isRequireSignature(): bool
    {
        return $this->requireSignature;
    }

    public function setRequireSignature(bool $requireSignature): static
    {
        $this->requireSignature = $requireSignature;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
        }
        return $this;
    }

    public function removeGroup(Group $group): static
    {
        $this->groups->removeElement($group);
        return $this;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSignatures(): Collection
    {
        return $this->signatures;
    }

    public function addSignature(CharteSignature $signature): static
    {
        if (!$this->signatures->contains($signature)) {
            $this->signatures->add($signature);
            $signature->setCharte($this);
        }
        return $this;
    }

    public function removeSignature(CharteSignature $signature): static
    {
        if ($this->signatures->removeElement($signature)) {
            if ($signature->getCharte() === $this) {
                $signature->setCharte(null);
            }
        }
        return $this;
    }

    public function hasUserSigned(User $user): bool
    {
        foreach ($this->signatures as $signature) {
            if ($signature->getUser()->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    public function isAccessibleToUser(?User $user): bool
    {
        $userRoles = $user ? $user->getRoles() : ['ROLE_VISITOR'];

        if ($user && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        if (!empty($this->roles)) {
            foreach ($userRoles as $role) {
                if (in_array($role, $this->roles)) {
                    return true;
                }
            }
        }

        if (!$user) {
            return false;
        }

        if ($this->groups->count() > 0) {
            foreach ($this->groups as $group) {
                if ($group->getUsers()->contains($user)) {
                    return true;
                }
            }
        }

        return false;
    }
}
