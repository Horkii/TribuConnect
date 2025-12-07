<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password = '';

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    private string $lastName = '';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\NotNull(message: "La date de naissance est requise.")]
    #[Assert\LessThanOrEqual('-18 years', message: "Vous devez avoir au moins 18 ans pour utiliser le site.")]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $cityOrRegion = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(type: 'string', length: 12, nullable: true)]
    private ?string $twoFactorCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $twoFactorExpiresAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetPasswordExpiresAt = null;

    #[ORM\ManyToMany(targetEntity: Family::class, inversedBy: 'members')]
    #[ORM\JoinTable(name: 'family_user')]
    private \Doctrine\Common\Collections\Collection $families;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->families = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = strtolower($email); return $this; }

    public function getUserIdentifier(): string { return $this->email; }
    public function getUsername(): string { return $this->email; }

    public function getRoles(): array { return array_values(array_unique(array_merge($this->roles, ['ROLE_USER']))); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getBirthDate(): ?\DateTimeImmutable { return $this->birthDate; }
    public function setBirthDate(?\DateTimeImmutable $date): self { $this->birthDate = $date; return $this; }

    public function getAge(): ?int
    {
        if (!$this->birthDate) { return null; }
        $today = new \DateTimeImmutable('today');
        $age = $this->birthDate->diff($today)->y;
        return $age;
    }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $postalCode): self { $this->postalCode = $postalCode; return $this; }

    public function getCityOrRegion(): ?string { return $this->cityOrRegion; }
    public function setCityOrRegion(?string $cityOrRegion): self { $this->cityOrRegion = $cityOrRegion; return $this; }

    /** @return \Doctrine\Common\Collections\Collection<int, Family> */
    public function getFamilies(): \Doctrine\Common\Collections\Collection { return $this->families; }
    public function addFamily(Family $family): self { if (!$this->families->contains($family)) { $this->families->add($family); } return $this; }
    public function removeFamily(Family $family): self { $this->families->removeElement($family); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getPhoneNumber(): ?string { return $this->phoneNumber; }
    public function setPhoneNumber(?string $phone): self { $this->phoneNumber = $phone ? preg_replace('/\s+/', '', $phone) : null; return $this; }

    public function isTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function setTwoFactorEnabled(bool $enabled): self { $this->twoFactorEnabled = $enabled; return $this; }

    public function getTwoFactorCode(): ?string { return $this->twoFactorCode; }
    public function setTwoFactorCode(?string $code): self { $this->twoFactorCode = $code; return $this; }

    public function getTwoFactorExpiresAt(): ?\DateTimeImmutable { return $this->twoFactorExpiresAt; }
    public function setTwoFactorExpiresAt(?\DateTimeImmutable $at): self { $this->twoFactorExpiresAt = $at; return $this; }

    public function getResetPasswordToken(): ?string { return $this->resetPasswordToken; }
    public function setResetPasswordToken(?string $token): self { $this->resetPasswordToken = $token; return $this; }

    public function getResetPasswordExpiresAt(): ?\DateTimeImmutable { return $this->resetPasswordExpiresAt; }
    public function setResetPasswordExpiresAt(?\DateTimeImmutable $at): self { $this->resetPasswordExpiresAt = $at; return $this; }
}
