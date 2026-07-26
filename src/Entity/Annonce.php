<?php

namespace App\Entity;

use App\Repository\AnnonceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnnonceRepository::class)]
class Annonce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    private ?string $content = null;

    #[ORM\Column]
    private bool $canBeHidden = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: AnnonceCategory::class, inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AnnonceCategory $category = null;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'annonce_group')]
    private Collection $groups;

    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $roles = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(targetEntity: AnnonceHidden::class, mappedBy: 'annonce', cascade: ['remove'], orphanRemoval: true)]
    private Collection $hiddenBy;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->hiddenBy = new ArrayCollection();
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

    public function isCanBeHidden(): bool
    {
        return $this->canBeHidden;
    }

    public function setCanBeHidden(bool $canBeHidden): static
    {
        $this->canBeHidden = $canBeHidden;
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

    public function getCategory(): ?AnnonceCategory
    {
        return $this->category;
    }

    public function setCategory(?AnnonceCategory $category): static
    {
        $this->category = $category;
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

    public function getHiddenBy(): Collection
    {
        return $this->hiddenBy;
    }

    public function addHiddenBy(AnnonceHidden $hidden): static
    {
        if (!$this->hiddenBy->contains($hidden)) {
            $this->hiddenBy->add($hidden);
            $hidden->setAnnonce($this);
        }
        return $this;
    }

    public function removeHiddenBy(AnnonceHidden $hidden): static
    {
        if ($this->hiddenBy->removeElement($hidden)) {
            if ($hidden->getAnnonce() === $this) {
                $hidden->setAnnonce(null);
            }
        }
        return $this;
    }

    public function isHiddenByUser(User $user): bool
    {
        foreach ($this->hiddenBy as $hidden) {
            if ($hidden->getUser()->getId() === $user->getId()) {
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
