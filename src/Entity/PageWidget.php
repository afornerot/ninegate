<?php

namespace App\Entity;

use App\Repository\PageWidgetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageWidgetRepository::class)]
#[ORM\Table(name: 'page_widget')]
class PageWidget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $location = 'R1C1';

    #[ORM\Column(name: 'widget_order', type: 'integer', options: ['default' => 0])]
    private int $widgetOrder = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $content = null;

    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'widgets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Page $page = null;

    #[ORM\ManyToOne(targetEntity: Widget::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Widget $widget = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $titleBgColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $titleFontColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $bodyBgColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $bodyFontColor = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $withBorder = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $withTitle = true;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\ManyToOne(targetEntity: Icon::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Icon $icon = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getWidgetOrder(): int
    {
        return $this->widgetOrder;
    }

    public function setWidgetOrder(int $widgetOrder): static
    {
        $this->widgetOrder = $widgetOrder;

        return $this;
    }

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    public function setWidget(?Widget $widget): static
    {
        $this->widget = $widget;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

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
}
