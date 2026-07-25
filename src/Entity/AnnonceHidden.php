<?php

namespace App\Entity;

use App\Repository\AnnonceHiddenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnonceHiddenRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_annonce_user', columns: ['annonce_id', 'user_id'])]
class AnnonceHidden
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Annonce::class, inversedBy: 'hiddenBy')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Annonce $annonce = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $hiddenAt = null;

    public function __construct()
    {
        $this->hiddenAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    public function setAnnonce(?Annonce $annonce): static
    {
        $this->annonce = $annonce;
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

    public function getHiddenAt(): ?\DateTimeInterface
    {
        return $this->hiddenAt;
    }

    public function setHiddenAt(\DateTimeInterface $hiddenAt): static
    {
        $this->hiddenAt = $hiddenAt;
        return $this;
    }
}
