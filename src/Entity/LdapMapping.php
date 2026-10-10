<?php

namespace App\Entity;

use App\Repository\LdapMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LdapMappingRepository::class)]
#[ORM\Table(name: 'ldap_mapping')]
#[ORM\UniqueConstraint(name: 'UNIQ_TYPE_ENTITY', fields: ['entityType', 'entityId'])]
class LdapMapping
{
    public const TYPE_USER  = 'user';
    public const TYPE_GROUP = 'group';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 255)]
    private string $ldapDn;

    #[ORM\Column(nullable: true)]
    private ?int $ldapGidNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapUid = null;

    #[ORM\Column]
    private \DateTimeImmutable $syncedAt;

    public function __construct()
    {
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getLdapDn(): string
    {
        return $this->ldapDn;
    }

    public function setLdapDn(string $ldapDn): static
    {
        $this->ldapDn = $ldapDn;
        return $this;
    }

    public function getLdapGidNumber(): ?int
    {
        return $this->ldapGidNumber;
    }

    public function setLdapGidNumber(?int $ldapGidNumber): static
    {
        $this->ldapGidNumber = $ldapGidNumber;
        return $this;
    }

    public function getLdapUid(): ?string
    {
        return $this->ldapUid;
    }

    public function setLdapUid(?string $ldapUid): static
    {
        $this->ldapUid = $ldapUid;
        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }
}