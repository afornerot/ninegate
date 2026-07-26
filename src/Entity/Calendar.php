<?php

namespace App\Entity;

use App\Repository\CalendarRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarRepository::class)]
class Calendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $calendarOrder = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roles = null;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'calendar_group')]
    private Collection $groups;

    #[ORM\OneToMany(targetEntity: CalendarEvent::class, mappedBy: 'calendar', cascade: ['remove'], orphanRemoval: true)]
    private Collection $events;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $this->color = $color; return $this; }
    public function getCalendarOrder(): int { return $this->calendarOrder; }
    public function setCalendarOrder(int $calendarOrder): static { $this->calendarOrder = $calendarOrder; return $this; }
    public function getRoles(): ?array { return $this->roles; }
    public function setRoles(?array $roles): static { $this->roles = $roles; return $this; }
    public function getGroups(): Collection { return $this->groups; }
    public function addGroup(Group $group): static { if (!$this->groups->contains($group)) { $this->groups->add($group); } return $this; }
    public function removeGroup(Group $group): static { $this->groups->removeElement($group); return $this; }
    public function getEvents(): Collection { return $this->events; }
    public function addEvent(CalendarEvent $event): static { if (!$this->events->contains($event)) { $this->events->add($event); $event->setCalendar($this); } return $this; }
    public function removeEvent(CalendarEvent $event): static { if ($this->events->removeElement($event)) { if ($event->getCalendar() === $this) { $event->setCalendar(null); } } return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
}
