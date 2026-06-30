<?php

namespace App\Entity\Ldap;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Ldap\LdapUserRepository::class)]
#[ORM\Table(name: 'users')]
class LdapUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $uidnumber = null;

    #[ORM\Column]
    private ?int $primarygroup = null;

    #[ORM\Column(length: 1024)]
    private ?string $othergroups = '';

    #[ORM\Column(length: 64)]
    private ?string $givenname = '';

    #[ORM\Column(length: 64)]
    private ?string $sn = '';

    #[ORM\Column(length: 254)]
    private ?string $mail = '';

    #[ORM\Column(length: 64)]
    private ?string $loginshell = '';

    #[ORM\Column(length: 64)]
    private ?string $homedirectory = '';

    #[ORM\Column(type: 'smallint')]
    private int $disabled = 0;

    #[ORM\Column(length: 64)]
    private ?string $passsha256 = '';

    #[ORM\Column(type: 'text')]
    private ?string $passbcrypt = '';

    #[ORM\Column(length: 64)]
    private ?string $otpsecret = '';

    #[ORM\Column(length: 128)]
    private ?string $yubikey = '';

    #[ORM\Column(type: 'text')]
    private ?string $sshkeys = '';

    #[ORM\Column(type: 'text')]
    private ?string $custattr = '{}';

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

    public function getUidnumber(): ?int
    {
        return $this->uidnumber;
    }

    public function setUidnumber(int $uidnumber): static
    {
        $this->uidnumber = $uidnumber;

        return $this;
    }

    public function getPrimarygroup(): ?int
    {
        return $this->primarygroup;
    }

    public function setPrimarygroup(int $primarygroup): static
    {
        $this->primarygroup = $primarygroup;

        return $this;
    }

    public function getOthergroups(): ?string
    {
        return $this->othergroups;
    }

    public function setOthergroups(?string $othergroups): static
    {
        $this->othergroups = $othergroups;

        return $this;
    }

    public function getGivenname(): ?string
    {
        return $this->givenname;
    }

    public function setGivenname(?string $givenname): static
    {
        $this->givenname = $givenname;

        return $this;
    }

    public function getSn(): ?string
    {
        return $this->sn;
    }

    public function setSn(?string $sn): static
    {
        $this->sn = $sn;

        return $this;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function setMail(?string $mail): static
    {
        $this->mail = $mail;

        return $this;
    }

    public function getLoginshell(): ?string
    {
        return $this->loginshell;
    }

    public function setLoginshell(?string $loginshell): static
    {
        $this->loginshell = $loginshell;

        return $this;
    }

    public function getHomedirectory(): ?string
    {
        return $this->homedirectory;
    }

    public function setHomedirectory(?string $homedirectory): static
    {
        $this->homedirectory = $homedirectory;

        return $this;
    }

    public function getDisabled(): int
    {
        return $this->disabled;
    }

    public function setDisabled(int $disabled): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getPasssha256(): ?string
    {
        return $this->passsha256;
    }

    public function setPasssha256(?string $passsha256): static
    {
        $this->passsha256 = $passsha256;

        return $this;
    }

    public function getPassbcrypt(): ?string
    {
        return $this->passbcrypt;
    }

    public function setPassbcrypt(?string $passbcrypt): static
    {
        $this->passbcrypt = $passbcrypt;

        return $this;
    }

    public function getSshkeys(): ?string
    {
        return $this->sshkeys;
    }

    public function setSshkeys(?string $sshkeys): static
    {
        $this->sshkeys = $sshkeys;

        return $this;
    }

    public function getCustattr(): ?string
    {
        return $this->custattr;
    }

    public function setCustattr(?string $custattr): static
    {
        $this->custattr = $custattr;

        return $this;
    }
}
