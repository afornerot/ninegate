<?php

namespace App\Entity;

use App\Repository\WidgetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WidgetRepository::class)]
#[ORM\Table(name: 'widget')]
class Widget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $route = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $titleBgColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $titleFontColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $bodyBgColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $bodyFontColor = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $withBorder = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $withTitle = true;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\ManyToOne(targetEntity: Icon::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Icon $icon = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

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

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getTitleBgColor(): ?string
    {
        return $this->titleBgColor;
    }

    public function setTitleBgColor(?string $titleBgColor): static
    {
        $this->titleBgColor = $titleBgColor;

        return $this;
    }

    public function getTitleFontColor(): ?string
    {
        return $this->titleFontColor;
    }

    public function setTitleFontColor(?string $titleFontColor): static
    {
        $this->titleFontColor = $titleFontColor;

        return $this;
    }

    public function getBodyBgColor(): ?string
    {
        return $this->bodyBgColor;
    }

    public function setBodyBgColor(?string $bodyBgColor): static
    {
        $this->bodyBgColor = $bodyBgColor;

        return $this;
    }

    public function getBodyFontColor(): ?string
    {
        return $this->bodyFontColor;
    }

    public function setBodyFontColor(?string $bodyFontColor): static
    {
        $this->bodyFontColor = $bodyFontColor;

        return $this;
    }

    public function isWithBorder(): bool
    {
        return $this->withBorder;
    }

    public function setWithBorder(bool $withBorder): static
    {
        $this->withBorder = $withBorder;

        return $this;
    }

    public function isWithTitle(): bool
    {
        return $this->withTitle;
    }

    public function setWithTitle(bool $withTitle): static
    {
        $this->withTitle = $withTitle;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getIcon(): ?Icon
    {
        return $this->icon;
    }

    public function setIcon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }
}
