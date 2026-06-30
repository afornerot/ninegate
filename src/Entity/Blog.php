<?php

namespace App\Entity;

use App\Repository\BlogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: BlogRepository::class)]
#[ORM\Table(name: 'blog')]
class Blog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(name: 'blog_order', type: 'integer', options: ['default' => 0])]
    private int $blogOrder = 0;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'blog_group')]
    private Collection $groups;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roles = null;

    #[ORM\OneToMany(targetEntity: BlogArticle::class, mappedBy: 'blog', orphanRemoval: true)]
    private Collection $articles;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->articles = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getBlogOrder(): int
    {
        return $this->blogOrder;
    }

    public function setBlogOrder(int $blogOrder): static
    {
        $this->blogOrder = $blogOrder;

        return $this;
    }

    /**
     * @return Collection<int, Group>
     */
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

    /**
     * @return Collection<int, BlogArticle>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(BlogArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setBlog($this);
        }

        return $this;
    }

    public function removeArticle(BlogArticle $article): static
    {
        if ($this->articles->removeElement($article)) {
            if ($article->getBlog() === $this) {
                $article->setBlog(null);
            }
        }

        return $this;
    }
}
