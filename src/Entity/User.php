<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Icon;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: "Ce nom d'utilisateur est deja pris.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_MASTER = 'ROLE_MASTER';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_VISITOR = 'ROLE_VISITOR';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Length(max: 180)]
    private ?string $username = null;

    #[ORM\Column(type: 'jsonb')]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Email(message: 'Veuillez entrer un email valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pseudo = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $needsPasswordUpgrade = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sha256Hash = null;

    #[ORM\OneToMany(targetEntity: Icon::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $icons;

    #[ORM\ManyToMany(targetEntity: Group::class, mappedBy: 'users')]
    private Collection $groups;

    #[ORM\OneToMany(targetEntity: UserGroup::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $userGroups;

    #[ORM\ManyToMany(targetEntity: Item::class, mappedBy: 'users')]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: Bookmark::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $bookmarks;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Page $favoritePage = null;

    public function __construct()
    {
        $this->icons = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->userGroups = new ArrayCollection();
        $this->items = new ArrayCollection();
        $this->bookmarks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar ? $this->avatar : 'medias/avatar/noavatar.png';
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function isNeedsPasswordUpgrade(): bool
    {
        return $this->needsPasswordUpgrade;
    }

    public function setNeedsPasswordUpgrade(bool $needsPasswordUpgrade): static
    {
        $this->needsPasswordUpgrade = $needsPasswordUpgrade;

        return $this;
    }

    public function getSha256Hash(): ?string
    {
        return $this->sha256Hash;
    }

    public function setSha256Hash(?string $sha256Hash): static
    {
        $this->sha256Hash = $sha256Hash;

        return $this;
    }

    public function getDisplayName(): string
    {
        if ($this->pseudo) {
            return $this->pseudo;
        }
        if ($this->firstname && $this->lastname) {
            return $this->firstname . ' ' . $this->lastname;
        }
        if ($this->firstname) {
            return $this->firstname;
        }
        return $this->username ?? '';
    }

    public function getIcons(): Collection
    {
        return $this->icons;
    }

    public function addIcon(Icon $icon): static
    {
        if (!$this->icons->contains($icon)) {
            $this->icons->add($icon);
            $icon->setUser($this);
        }

        return $this;
    }

    public function removeIcon(Icon $icon): static
    {
        $this->icons->removeElement($icon);

        return $this;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->addUser($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
            $group->removeUser($this);
        }

        return $this;
    }

    public function getUserGroups(): Collection
    {
        return $this->userGroups;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Item $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->addUser($this);
        }

        return $this;
    }

    public function removeItem(Item $item): static
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            $item->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Bookmark>
     */
    public function getBookmarks(): Collection
    {
        return $this->bookmarks;
    }

    public function addBookmark(Bookmark $bookmark): static
    {
        if (!$this->bookmarks->contains($bookmark)) {
            $this->bookmarks->add($bookmark);
            $bookmark->setUser($this);
        }

        return $this;
    }

    public function removeBookmark(Bookmark $bookmark): static
    {
        if ($this->bookmarks->removeElement($bookmark)) {
            if ($bookmark->getUser() === $this) {
                $bookmark->setUser(null);
            }
        }

        return $this;
    }

    public function getFavoritePage(): ?Page
    {
        return $this->favoritePage;
    }

    public function setFavoritePage(?Page $favoritePage): static
    {
        $this->favoritePage = $favoritePage;
        return $this;
    }
}
