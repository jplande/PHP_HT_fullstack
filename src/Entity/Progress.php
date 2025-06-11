<?php

namespace App\Entity;

use App\Repository\ProgressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ProgressRepository::class)]
#[ORM\Index(name: 'idx_progress_date', columns: ['date'])]
#[ORM\Index(name: 'idx_progress_goal_date', columns: ['goal_id', 'date'])]
#[UniqueEntity(
    fields: ['goal', 'metric', 'date'],
    message: 'Une progression existe déjà pour cette métrique à cette date'
)]
class Progress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['progress', 'analytics'])]
    private ?int $id = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['progress', 'analytics', 'dashboard'])]
    #[Assert\NotNull(message: 'La valeur est obligatoire')]
    private ?float $value = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['progress', 'analytics', 'dashboard'])]
    #[Assert\NotNull(message: 'La date est obligatoire')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['progress'])]
    #[Assert\Length(max: 1000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['progress'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['progress'])]
    private ?array $metadata = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['progress'])]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'La difficulté doit être entre {{ min }} et {{ max }}')]
    private ?int $difficultyRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['progress'])]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'La satisfaction doit être entre {{ min }} et {{ max }}')]
    private ?int $satisfactionRating = null;

    #[ORM\ManyToOne(targetEntity: Goal::class, inversedBy: 'progressEntries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['progress'])]
    private ?Goal $goal = null;

    #[ORM\ManyToOne(targetEntity: Metric::class, inversedBy: 'progressEntries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['progress', 'analytics'])]
    private ?Metric $metric = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'progressEntries')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Session $session = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->date = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
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

    public function getSatisfactionRating(): ?int
    {
        return $this->satisfactionRating;
    }

    public function setSatisfactionRating(?int $satisfactionRating): static
    {
        $this->satisfactionRating = $satisfactionRating;
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

    public function getMetric(): ?Metric
    {
        return $this->metric;
    }

    public function setMetric(?Metric $metric): static
    {
        $this->metric = $metric;
        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    /**
     * Calcule la différence avec la progression précédente
     */
    #[Groups(['analytics'])]
    public function getDifferenceFromPrevious(): ?float
    {
        if (!$this->goal || !$this->metric) {
            return null;
        }

        // Recherche la progression précédente
        $previousProgress = null;
        $previousDate = null;

        foreach ($this->goal->getProgressEntries() as $progress) {
            if ($progress->getMetric() === $this->metric &&
                $progress->getDate() < $this->date &&
                (!$previousDate || $progress->getDate() > $previousDate)) {
                $previousProgress = $progress;
                $previousDate = $progress->getDate();
            }
        }

        return $previousProgress ? $this->value - $previousProgress->getValue() : null;
    }

    /**
     * Calcule le pourcentage de progression vers l'objectif
     */
    #[Groups(['analytics', 'dashboard'])]
    public function getProgressPercentage(): float
    {
        if (!$this->metric) {
            return 0.0;
        }

        $target = $this->metric->getTargetValue();
        $initial = $this->metric->getInitialValue();

        if ($target == $initial) {
            return 100.0;
        }

        return min(100.0, max(0.0, (($this->value - $initial) / ($target - $initial)) * 100));
    }

    /**
     * Vérifie si cette progression atteint l'objectif
     */
    #[Groups(['analytics'])]
    public function isTargetReached(): bool
    {
        if (!$this->metric) {
            return false;
        }

        return match ($this->metric->getEvolutionType()) {
            'increase' => $this->value >= $this->metric->getTargetValue(),
            'decrease' => $this->value <= $this->metric->getTargetValue(),
            'maintain' => abs($this->value - $this->metric->getTargetValue()) <= 0.1,
            default => false,
        };
    }

    /**
     * Retourne la valeur formatée avec l'unité
     */
    #[Groups(['progress', 'dashboard'])]
    public function getFormattedValue(): string
    {
        if (!$this->metric) {
            return (string) $this->value;
        }

        $formattedValue = is_float($this->value) && floor($this->value) != $this->value
            ? number_format($this->value, 2, ',', ' ')
            : number_format($this->value, 0, ',', ' ');

        return sprintf('%s %s', $formattedValue, $this->metric->getUnit());
    }

    /**
     * Ajoute une métadonnée
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Récupère une métadonnée
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Valide que la valeur est cohérente avec le type d'évolution
     */
    #[Assert\Callback]
    public function validateValueCoherence(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (!$this->metric || $this->value === null) {
            return;
        }

        $initial = $this->metric->getInitialValue();
        $target = $this->metric->getTargetValue();

        // Avertissement si la valeur dépasse largement l'objectif (peut être normal)
        switch ($this->metric->getEvolutionType()) {
            case 'increase':
                if ($this->value > $target * 1.5) {
                    $context->buildViolation('Cette valeur semble très élevée par rapport à l\'objectif fixé')
                        ->atPath('value')
                        ->addViolation();
                }
                break;
            case 'decrease':
                if ($this->value < $target * 0.5) {
                    $context->buildViolation('Cette valeur semble très faible par rapport à l\'objectif fixé')
                        ->atPath('value')
                        ->addViolation();
                }
                break;
        }
    }

    /**
     * Valide que la date n'est pas dans le futur
     */
    #[Assert\Callback]
    public function validateDate(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->date && $this->date > new \DateTime()) {
            $context->buildViolation('La date de progression ne peut pas être dans le futur')
                ->atPath('date')
                ->addViolation();
        }
    }
}
