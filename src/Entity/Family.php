<?php

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
#[ORM\Table(name: 'families')]
class Family
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'families')]
    private Collection $members;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $owner = null;

    #[ORM\OneToMany(mappedBy: 'family', targetEntity: Invitation::class, cascade: ['persist', 'remove'])]
    private Collection $invitations;

    #[ORM\OneToMany(mappedBy: 'family', targetEntity: Event::class, cascade: ['persist', 'remove'])]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'family', targetEntity: Message::class, cascade: ['persist', 'remove'])]
    private Collection $messages;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): self { $this->owner = $owner; return $this; }

    /** @return Collection<int, User> */
    public function getMembers(): Collection { return $this->members; }

    /** @return Collection<int, Invitation> */
    public function getInvitations(): Collection { return $this->invitations; }

    /** @return Collection<int, Event> */
    public function getEvents(): Collection { return $this->events; }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }
}
