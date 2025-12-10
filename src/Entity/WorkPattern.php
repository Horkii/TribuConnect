<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'work_patterns')]
class WorkPattern
{
    public const SHIFT_REST = 0;
    public const SHIFT_MORNING = 1;
    public const SHIFT_AFTERNOON = 2;
    public const SHIFT_NIGHT = 3;
    public const SHIFT_HOLIDAY = 4; // congé spécifique (visuel distinct)
    public const SHIFT_DAY = 5;      // journée complète
    public const SHIFT_REMOTE = 6;   // télétravail
    public const SHIFT_TRAVEL = 7;   // déplacement professionnel

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Family $family = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null; // la personne concernée

    #[ORM\Column(type: 'smallint')]
    private int $cycleLength = 21; // 7..21 (par défaut 21 jours)

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate; // date d'ancrage du cycle

    // Tableau d'entiers (0 repos, 1 matin, 2 après-midi, 3 nuit)
    #[ORM\Column(type: 'json')]
    private array $pattern = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->startDate = new \DateTimeImmutable('today');
        $this->pattern = [];
    }

    public function getId(): ?int { return $this->id; }
    public function getFamily(): ?Family { return $this->family; }
    public function setFamily(Family $f): self { $this->family = $f; return $this; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $u): self { $this->owner = $u; return $this; }
    public function getCycleLength(): int { return $this->cycleLength; }
    public function setCycleLength(int $n): self { $this->cycleLength = $n; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $d): self { $this->startDate = $d; return $this; }
    public function getPattern(): array { return $this->pattern; }
    public function setPattern(array $p): self { $this->pattern = array_values($p); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
