<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 128)]
    private string $sessId;

    #[ORM\Column(type: 'blob')]
    private $sessData;

    #[ORM\Column(type: 'integer')]
    private int $sessLifetime;

    #[ORM\Column(type: 'integer')]
    private int $sessTime;

    public function getSessId(): string
    {
        return $this->sessId;
    }

    public function getSessData()
    {
        return $this->sessData;
    }

    public function setSessData($sessData): void
    {
        $this->sessData = $sessData;
    }

    public function getSessLifetime(): int
    {
        return $this->sessLifetime;
    }

    public function setSessLifetime(int $sessLifetime): void
    {
        $this->sessLifetime = $sessLifetime;
    }

    public function getSessTime(): int
    {
        return $this->sessTime;
    }

    public function setSessTime(int $sessTime): void
    {
        $this->sessTime = $sessTime;
    }
}
