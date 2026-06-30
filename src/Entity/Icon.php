<?php

namespace App\Entity;

use App\Repository\IconRepository;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IconRepository::class)]
class Icon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $route = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tags = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'icon')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    public function __toString(): string
    {
        return $this->route ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(?string $tags): static
    {
        $this->tags = $tags;
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
}