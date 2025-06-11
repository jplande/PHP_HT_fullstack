<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Index(name: 'idx_session_start_time', columns: ['start_time'])]
#[ORM\Index(name: 'idx_session_goal', columns: ['goal_id'])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['session', 'progress'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['session', 'dashboard'])]
    #[Assert\NotNull(message: 'L\'heure de début est obligatoire')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['session', 'dashboard'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session', 'dashboard'])]
    #[Assert\Range(min: 0, max: 86400, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} secondes')]
    private ?int $duration = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['session', 'dashboard'])]
    private ?bool $completed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['session'])]
    #[Assert\Length(max: 2000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['session'])]
    private ?array $sessionData = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session'])]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'L\'intensité doit être entre {{ min }} et {{ max }}')]
    private ?int $intensityRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session'])]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'La satisfaction doit être entre {{ min }} et {{ max }}')]
    private ?int $satisfactionRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session'])]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'La difficulté doit être entre {{ min }} et {{ max }}')]
    private ?int $difficultyRating = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['session'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['session'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Goal::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session'])]
    private ?Goal $goal = null;

    /**
     * @var Collection<int, Progress>
     */
    #[ORM\OneToMany(targetEntity: Progress::class, mappedBy: 'session', cascade: ['persist', 'remove'])]
    #[Groups(['session'])]
    private Collection $progressEntries;

    public function __construct()
    {
        $this->progressEntries = new ArrayCollection();
        $this->completed = false;
        $this->createdAt = new \DateTime();
        $this->startTime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        // Calcul automatique de la durée
        if ($endTime && $this->startTime) {
            $this->duration = $endTime->getTimestamp() - $this->startTime->getTimestamp();
        }

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getCompleted(): ?bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): static
    {
        $this->completed = $completed;

        // Si la session est marquée comme complétée et qu'il n'y a pas d'heure de fin, on la définit maintenant
        if ($completed && !$this->endTime) {
            $this->setEndTime(new \DateTime());
        }

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getSessionData(): ?array
    {
        return $this->sessionData;
    }

    public function setSessionData(?array $sessionData): static
    {
        $this->sessionData = $sessionData;
        return $this;
    }

    public function getIntensityRating(): ?int
    {
        return $this->intensityRating;
    }

    public function setIntensityRating(?int $intensityRating): static
    {
        $this->intensityRating = $intensityRating;
        return $this;
    }

    public function getSatisfactionRating(): ?int
    {
        return $this->satisfactionRating;
    }

    public function setSatisfactionRating(?int $satisfactionRating): static
    {
        $this->satisfactionRating = $satisfactionRating;
        return $this;
    }

    public function getDifficultyRating(): ?int
    {
        return $this->difficultyRating;
    }

    public function setDifficultyRating(?int $difficultyRating): static
    {
        $this->difficultyRating = $difficultyRating;
        return $this;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getGoal(): ?Goal
    {
        return $this->goal;
    }

    public function setGoal(?Goal $goal): static
    {
        $this->goal = $goal;
        return $this;
    }

    /**
     * @return Collection<int, Progress>
     */
    public function getProgressEntries(): Collection
    {
        return $this->progressEntries;
    }

    public function addProgressEntry(Progress $progressEntry): static
    {
        if (!$this->progressEntries->contains($progressEntry)) {
            $this->progressEntries->add($progressEntry);
            $progressEntry->setSession($this);
        }
        return $this;
    }

    public function removeProgressEntry(Progress $progressEntry): static
    {
        if ($this->progressEntries->removeElement($progressEntry)) {
            if ($progressEntry->getSession() === $this) {
                $progressEntry->setSession(null);
            }
        }
        return $this;
    }

    /**
     * Retourne la durée formatée (HH:MM:SS)
     */
    #[Groups(['session', 'dashboard'])]
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '00:00:00';
        }

        $hours = intval($this->duration / 3600);
        $minutes = intval(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Retourne la durée en minutes
     */
    #[Groups(['session', 'dashboard'])]
    public function getDurationInMinutes(): int
    {
        return $this->duration ? intval($this->duration / 60) : 0;
    }

    /**
     * Vérifie si la session est en cours
     */
    #[Groups(['session', 'dashboard'])]
    public function isInProgress(): bool
    {
        return $this->startTime && !$this->endTime && !$this->completed;
    }

    /**
     * Termine la session
     */
    public function finish(): static
    {
        if (!$this->endTime) {
            $this->setEndTime(new \DateTime());
        }
        $this->setCompleted(true);
        return $this;
    }

    /**
     * Ajoute des données de session
     */
    public function addSessionData(string $key, mixed $value): static
    {
        $data = $this->sessionData ?? [];
        $data[$key] = $value;
        $this->sessionData = $data;
        return $this;
    }

    /**
     * Récupère une donnée de session
     */
    public function getSessionDataValue(string $key): mixed
    {
        return $this->sessionData[$key] ?? null;
    }

    /**
     * Calcule la note moyenne de la session
     */
    #[Groups(['session', 'dashboard'])]
    public function getAverageRating(): ?float
    {
        $ratings = array_filter([
            $this->intensityRating,
            $this->satisfactionRating,
            $this->difficultyRating
        ]);

        if (empty($ratings)) {
            return null;
        }

        return array_sum($ratings) / count($ratings);
    }

    /**
     * Retourne le nombre de progressions enregistrées dans cette session
     */
    #[Groups(['session', 'dashboard'])]
    public function getProgressCount(): int
    {
        return $this->progressEntries->count();
    }

    /**
     * Valide que l'heure de fin est après l'heure de début
     */
    #[Assert\Callback]
    public function validateTimeConsistency(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->startTime && $this->endTime && $this->endTime <= $this->startTime) {
            $context->buildViolation('L\'heure de fin doit être après l\'heure de début')
                ->atPath('endTime')
                ->addViolation();
        }
    }

    /**
     * Valide que la durée est cohérente avec les heures de début/fin
     */
    #[Assert\Callback]
    public function validateDurationConsistency(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->startTime && $this->endTime && $this->duration !== null) {
            $calculatedDuration = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
            if (abs($calculatedDuration - $this->duration) > 60) { // Tolérance de 1 minute
                $context->buildViolation('La durée ne correspond pas aux heures de début et fin')
                    ->atPath('duration')
                    ->addViolation();
            }
        }
    }
}
