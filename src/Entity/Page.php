<?php

namespace App\Entity;

use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'page')]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'page_order', type: 'integer', options: ['default' => 0])]
    private int $pageOrder = 0;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'page_group')]
    private Collection $groups;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roles = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: PageTemplate::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PageTemplate $pageTemplate = null;

    #[ORM\OneToMany(targetEntity: PageWidget::class, mappedBy: 'page', orphanRemoval: true)]
    private Collection $widgets;

    public function __construct()
    {
        $this->pageOrder = 0;
        $this->groups = new ArrayCollection();
        $this->widgets = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPageOrder(): int
    {
        return $this->pageOrder;
    }

    public function setPageOrder(int $pageOrder): static
    {
        $this->pageOrder = $pageOrder;

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
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
        }

        return $this;
    }

    public function clearGroups(): static
    {
        $this->groups->clear();

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

    public function getPageTemplate(): ?PageTemplate
    {
        return $this->pageTemplate;
    }

    public function setPageTemplate(?PageTemplate $pageTemplate): static
    {
        $this->pageTemplate = $pageTemplate;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getWidgets(): Collection
    {
        return $this->widgets;
    }

    public function addWidget(PageWidget $widget): static
    {
        if (!$this->widgets->contains($widget)) {
            $this->widgets->add($widget);
            $widget->setPage($this);
        }

        return $this;
    }

    public function removeWidget(PageWidget $widget): static
    {
        if ($this->widgets->removeElement($widget)) {
            if ($widget->getPage() === $this) {
                $widget->setPage(null);
            }
        }

        return $this;
    }

    #[Assert\Callback]
    public function validateGroupOrUser(ExecutionContextInterface $context): void
    {
        $hasGroupsOrRoles = !$this->getGroups()->isEmpty() || !empty($this->getRoles());
        $hasUser = null !== $this->getUser();

        if ($hasGroupsOrRoles && $hasUser) {
            $context->buildViolation('La page ne peut pas être associée à la fois à des groupes/roles et à un utilisateur.')
                ->atPath('user')
                ->addViolation();
        }

        if (!$hasGroupsOrRoles && !$hasUser) {
            if (null === $this->getId()) {
                return;
            }
            $context->buildViolation('La page doit être associée soit à des groupes/roles soit à un utilisateur.')
                ->addViolation();
        }
    }
}
