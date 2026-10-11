<?php

namespace App\Entity;

use App\Repository\CronRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CronRepository::class)]
class Cron
{
    public const STATUT_TODO = 0;
    public const STATUT_RUNNING = 1;
    public const STATUT_OK = 2;
    public const STATUT_KO = 3;
    public const STATUT_DISABLED = 4;

    public const STATUTS = [
        'A executer' => self::STATUT_TODO,
        'Exécution en cours' => self::STATUT_RUNNING,
        'OK' => self::STATUT_OK,
        'KO' => self::STATUT_KO,
        'Désactivé' => self::STATUT_DISABLED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $command = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $statut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $startexecdate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endexecdate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $nextexecdate = null;

    #[ORM\Column(nullable: true)]
    private ?int $repeatcall = null;

    #[ORM\Column(nullable: true)]
    private ?int $repeatexec = null;

    #[ORM\Column(nullable: true)]
    private ?int $repeatinterval = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jsonargument = null;

    public function getStatutlabel(): string
    {
        return match ($this->statut) {
            self::STATUT_TODO => 'A éxécuter',
            self::STATUT_RUNNING => 'Exécution en cours',
            self::STATUT_OK => 'OK',
            self::STATUT_KO => 'KO',
            self::STATUT_DISABLED => 'Désactivé',
            default => 'Inconnu',
        };
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function setCommand(string $command): static
    {
        $this->command = $command;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function setStatut(int $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getStartexecdate(): ?\DateTime
    {
        return $this->startexecdate;
    }

    public function setStartexecdate(?\DateTime $startexecdate): static
    {
        $this->startexecdate = $startexecdate;

        return $this;
    }

    public function getEndexecdate(): ?\DateTime
    {
        return $this->endexecdate;
    }

    public function setEndexecdate(?\DateTime $endexecdate): static
    {
        $this->endexecdate = $endexecdate;

        return $this;
    }

    public function getNextexecdate(): ?\DateTime
    {
        return $this->nextexecdate;
    }

    public function setNextexecdate(?\DateTime $nextexecdate): static
    {
        $this->nextexecdate = $nextexecdate;

        return $this;
    }

    public function getRepeatcall(): ?int
    {
        return $this->repeatcall;
    }

    public function setRepeatcall(?int $repeatcall): static
    {
        $this->repeatcall = $repeatcall;

        return $this;
    }

    public function getRepeatexec(): ?int
    {
        return $this->repeatexec;
    }

    public function setRepeatexec(?int $repeatexec): static
    {
        $this->repeatexec = $repeatexec;

        return $this;
    }

    public function getRepeatinterval(): ?int
    {
        return $this->repeatinterval;
    }

    public function setRepeatinterval(?int $repeatinterval): static
    {
        $this->repeatinterval = $repeatinterval;

        return $this;
    }

    public function getJsonargument(): ?string
    {
        return $this->jsonargument;
    }

    public function setJsonargument(?string $jsonargument): static
    {
        $this->jsonargument = $jsonargument;

        return $this;
    }
}
