<?php

namespace App\Entity;

use App\Repository\CharteSignatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharteSignatureRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_charte_user', columns: ['charte_id', 'user_id'])]
class CharteSignature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Charte::class, inversedBy: 'signatures')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Charte $charte = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $signedAt = null;

    public function __construct()
    {
        $this->signedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharte(): ?Charte
    {
        return $this->charte;
    }

    public function setCharte(?Charte $charte): static
    {
        $this->charte = $charte;
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

    public function getSignedAt(): ?\DateTimeInterface
    {
        return $this->signedAt;
    }

    public function setSignedAt(\DateTimeInterface $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }
}
