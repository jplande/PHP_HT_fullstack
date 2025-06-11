<?php

namespace App\Entity;

use App\Repository\MetricRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MetricRepository::class)]
class Metric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal', 'metric', 'progress'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['goal', 'metric', 'progress', 'dashboard'])]
    #[Assert\NotBlank(message: 'Le nom de la métrique est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Groups(['goal', 'metric', 'progress', 'dashboard'])]
    #[Assert\NotBlank(message: 'L\'unité est obligatoire')]
    #[Assert\Length(max: 50, maxMessage: 'L\'unité ne peut pas dépasser {{ limit }} caractères')]
    private ?string $unit = null;

    #[ORM\Column(length: 20)]
    #[Groups(['goal', 'metric'])]
    #[Assert\Choice(
        choices: ['increase', 'decrease', 'maintain'],
        message: 'Le type d\'évolution doit être increase, decrease ou maintain'
    )]
    private ?string $evolutionType = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['goal', 'metric', 'dashboard'])]
    #[Assert\NotNull(message: 'La valeur initiale est obligatoire')]
    private ?float $initialValue = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['goal', 'metric', 'dashboard'])]
    #[Assert\NotNull(message: 'La valeur cible est obligatoire')]
    private ?float $targetValue = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['goal', 'metric'])]
    private ?bool $isPrimary = false;

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['goal', 'metric'])]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format hexadécimal (#RRGGBB)'
    )]
    private ?string $color = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['goal', 'metric'])]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'L\'ordre doit être entre {{ min }} et {{ max }}')]
    private ?int $displayOrder = 0;

    #[ORM\ManyToOne(targetEntity: Goal::class, inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Goal $goal = null;

    /**
     * @var Collection<int, Progress>
     */
    #[ORM\OneToMany(targetEntity: Progress::class, mappedBy: 'metric', cascade: ['persist', 'remove'])]
    private Collection $progressEntries;

    public function __construct()
    {
        $this->progressEntries = new ArrayCollection();
        $this->isPrimary = false;
        $this->displayOrder = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getEvolutionType(): ?string
    {
        return $this->evolutionType;
    }

    public function setEvolutionType(string $evolutionType): static
    {
        $this->evolutionType = $evolutionType;
        return $this;
    }

    public function getInitialValue(): ?float
    {
        return $this->initialValue;
    }

    public function setInitialValue(float $initialValue): static
    {
        $this->initialValue = $initialValue;
        return $this;
    }

    public function getTargetValue(): ?float
    {
        return $this->targetValue;
    }

    public function setTargetValue(float $targetValue): static
    {
        $this->targetValue = $targetValue;
        return $this;
    }

    public function getIsPrimary(): ?bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;
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
            $progressEntry->setMetric($this);
        }
        return $this;
    }

    public function removeProgressEntry(Progress $progressEntry): static
    {
        if ($this->progressEntries->removeElement($progressEntry)) {
            if ($progressEntry->getMetric() === $this) {
                $progressEntry->setMetric(null);
            }
        }
        return $this;
    }

    /**
     * Calcule la progression en pourcentage vers l'objectif
     */
    #[Groups(['dashboard'])]
    public function getProgressPercentage(): float
    {
        $latestProgress = $this->getLatestProgress();
        if (!$latestProgress) {
            return 0.0;
        }

        $current = $latestProgress->getValue();
        $target = $this->targetValue;
        $initial = $this->initialValue;

        if ($target == $initial) {
            return 100.0;
        }

        return min(100.0, max(0.0, (($current - $initial) / ($target - $initial)) * 100));
    }

    /**
     * Obtient la dernière valeur enregistrée
     */
    #[Groups(['dashboard'])]
    public function getCurrentValue(): ?float
    {
        $latestProgress = $this->getLatestProgress();
        return $latestProgress ? $latestProgress->getValue() : $this->initialValue;
    }

    /**
     * Obtient la dernière progression enregistrée
     */
    public function getLatestProgress(): ?Progress
    {
        if ($this->progressEntries->isEmpty()) {
            return null;
        }

        $latest = null;
        $latestDate = null;

        foreach ($this->progressEntries as $progress) {
            if (!$latestDate || $progress->getDate() > $latestDate) {
                $latestDate = $progress->getDate();
                $latest = $progress;
            }
        }

        return $latest;
    }

    /**
     * Vérifie si l'objectif est atteint
     */
    #[Groups(['dashboard'])]
    public function isTargetReached(): bool
    {
        $currentValue = $this->getCurrentValue();
        if ($currentValue === null) {
            return false;
        }

        return match ($this->evolutionType) {
            'increase' => $currentValue >= $this->targetValue,
            'decrease' => $currentValue <= $this->targetValue,
            'maintain' => abs($currentValue - $this->targetValue) <= 0.1, // tolérance de 0.1
            default => false,
        };
    }

    /**
     * Calcule la différence avec l'objectif
     */
    #[Groups(['dashboard'])]
    public function getDifferenceFromTarget(): float
    {
        $currentValue = $this->getCurrentValue();
        if ($currentValue === null) {
            return $this->targetValue - $this->initialValue;
        }

        return $this->targetValue - $currentValue;
    }

    /**
     * Retourne le nom complet avec unité
     */
    public function getFullName(): string
    {
        return sprintf('%s (%s)', $this->name, $this->unit);
    }

    /**
     * Valide la cohérence des valeurs selon le type d'évolution
     */
    #[Assert\Callback]
    public function validateValues(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->initialValue === null || $this->targetValue === null || $this->evolutionType === null) {
            return;
        }

        switch ($this->evolutionType) {
            case 'increase':
                if ($this->targetValue <= $this->initialValue) {
                    $context->buildViolation('Pour une évolution croissante, la valeur cible doit être supérieure à la valeur initiale')
                        ->atPath('targetValue')
                        ->addViolation();
                }
                break;
            case 'decrease':
                if ($this->targetValue >= $this->initialValue) {
                    $context->buildViolation('Pour une évolution décroissante, la valeur cible doit être inférieure à la valeur initiale')
                        ->atPath('targetValue')
                        ->addViolation();
                }
                break;
        }
    }
}
