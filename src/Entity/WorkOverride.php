<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'work_overrides', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_pattern_date', columns: ['pattern_id', 'date'])
])]
class WorkOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkPattern::class)]
    #[ORM\JoinColumn(name: 'pattern_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?WorkPattern $pattern = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    // 0 repos, 1 matin, 2 après-midi, 3 nuit
    #[ORM\Column(type: 'smallint')]
    private int $value = 0;

    public function getId(): ?int { return $this->id; }
    public function getPattern(): ?WorkPattern { return $this->pattern; }
    public function setPattern(WorkPattern $p): self { $this->pattern = $p; return $this; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $d): self { $this->date = $d; return $this; }
    public function getValue(): int { return $this->value; }
    public function setValue(int $v): self { $this->value = $v; return $this; }
}
