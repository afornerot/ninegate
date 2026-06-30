<?php

namespace App\Entity\Ldap;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Ldap\LdapGroupRepository::class)]
#[ORM\Table(name: 'ldapgroups')]
class LdapGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $gidnumber = null;

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

    public function getGidnumber(): ?int
    {
        return $this->gidnumber;
    }

    public function setGidnumber(int $gidnumber): static
    {
        $this->gidnumber = $gidnumber;

        return $this;
    }
}
