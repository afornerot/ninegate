<?php

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_BOOL = 'bool';
    public const TYPE_COLOR = 'color';
    public const TYPE_TEXT = 'text';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_FONT = 'font';
    public const TYPE_LOGO = 'logo';

    public const TYPES = [
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_BOOL,
        self::TYPE_COLOR,
        self::TYPE_TEXT,
        self::TYPE_FLOAT,
        self::TYPE_DATETIME,
        self::TYPE_FONT,
        self::TYPE_LOGO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_STRING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $configGroup = null;

    #[ORM\Column(name: 'config_order')]
    private ?int $order = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $configMasterCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $configMasterValue = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getValue(): ?string
    {
        return $this->value ?? $this->defaultValue;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getRawValue(): ?string
    {
        return $this->value;
    }

    public function setRawValue(?string $rawValue): static
    {
        $this->value = $rawValue;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getConfigGroup(): ?string
    {
        return $this->configGroup;
    }

    public function setConfigGroup(?string $configGroup): static
    {
        $this->configGroup = $configGroup;

        return $this;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getConfigMasterCode(): ?string
    {
        return $this->configMasterCode;
    }

    public function setConfigMasterCode(?string $configMasterCode): static
    {
        $this->configMasterCode = $configMasterCode;

        return $this;
    }

    public function getConfigMasterValue(): ?string
    {
        return $this->configMasterValue;
    }

    public function setConfigMasterValue(?string $configMasterValue): static
    {
        $this->configMasterValue = $configMasterValue;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(?string $masterConfigValue): bool
    {
        if (null === $this->configMasterCode || null === $this->configMasterValue) {
            return true;
        }

        return $masterConfigValue === $this->configMasterValue;
    }

    public function getTypedValue(): mixed
    {
        $value = $this->getRawValue() ?? $this->defaultValue;

        return match ($this->type) {
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            self::TYPE_INT => null !== $value ? (int) $value : null,
            self::TYPE_FLOAT => null !== $value ? (float) $value : null,
            default => $value,
        };
    }
}
