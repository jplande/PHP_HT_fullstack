<?php

namespace App\Entity;

use App\Repository\GoalRepository;
use App\Traits\StatisticsPropertiesTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: GoalRepository::class)]
class Goal
{
    // IMPORTANT: Le trait contient déjà createdAt, updatedAt et status
    use StatisticsPropertiesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal', 'progress', 'dashboard'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['goal', 'progress', 'dashboard'])]
    #[Assert\NotBlank(message: 'Le titre de l\'objectif est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['goal'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Groups(['goal', 'dashboard'])]
    #[Assert\Choice(choices: ['daily', 'weekly', 'monthly'], message: 'La fréquence doit être daily, weekly ou monthly')]
    private ?string $frequencyType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['goal'])]
    #[Assert\NotNull(message: 'La date de début est obligatoire')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['goal'])]
    private ?\DateTimeInterface $endDate = null;

    // SUPPRIMÉ: createdAt et updatedAt car ils viennent du trait

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'goals')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goal', 'dashboard'])]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'goals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, Metric>
     */
    #[ORM\OneToMany(targetEntity: Metric::class, mappedBy: 'goal', cascade: ['persist', 'remove'])]
    #[Groups(['goal'])]
    private Collection $metrics;

    /**
     * @var Collection<int, Progress>
     */
    #[ORM\OneToMany(targetEntity: Progress::class, mappedBy: 'goal', cascade: ['persist', 'remove'])]
    private Collection $progressEntries;

    /**
     * @var Collection<int, Session>
     */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'goal', cascade: ['persist', 'remove'])]
    private Collection $sessions;

    public function __construct()
    {
        $this->metrics = new ArrayCollection();
        $this->progressEntries = new ArrayCollection();
        $this->sessions = new ArrayCollection();

        // IMPORTANT: Initialiser le status depuis le trait
        $this->setStatus('active');

        // Les dates sont gérées par le trait via @PrePersist
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getFrequencyType(): ?string
    {
        return $this->frequencyType;
    }

    public function setFrequencyType(string $frequencyType): static
    {
        $this->frequencyType = $frequencyType;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    // SUPPRIMÉ: getCreatedAt, setCreatedAt, getUpdatedAt, setUpdatedAt car ils viennent du trait

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, Metric>
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(Metric $metric): static
    {
        if (!$this->metrics->contains($metric)) {
            $this->metrics->add($metric);
            $metric->setGoal($this);
        }
        return $this;
    }

    public function removeMetric(Metric $metric): static
    {
        if ($this->metrics->removeElement($metric)) {
            if ($metric->getGoal() === $this) {
                $metric->setGoal(null);
            }
        }
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
            $progressEntry->setGoal($this);
        }
        return $this;
    }

    public function removeProgressEntry(Progress $progressEntry): static
    {
        if ($this->progressEntries->removeElement($progressEntry)) {
            if ($progressEntry->getGoal() === $this) {
                $progressEntry->setGoal(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(Session $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setGoal($this);
        }
        return $this;
    }

    public function removeSession(Session $session): static
    {
        if ($this->sessions->removeElement($session)) {
            if ($session->getGoal() === $this) {
                $session->setGoal(null);
            }
        }
        return $this;
    }

    // SUPPRIMÉ: @PreUpdate car déjà dans le trait

    /**
     * Méthode utilitaire pour obtenir la métrique principale
     */
    public function getPrimaryMetric(): ?Metric
    {
        foreach ($this->metrics as $metric) {
            if ($metric->getIsPrimary()) {
                return $metric;
            }
        }
        return $this->metrics->first() ?: null;
    }

    /**
     * Calcule le pourcentage de completion basé sur la métrique principale
     */
    #[Groups(['dashboard'])]
    public function getCompletionPercentage(): float
    {
        $primaryMetric = $this->getPrimaryMetric();
        if (!$primaryMetric) {
            return 0.0;
        }

        $latestProgress = $this->getLatestProgress($primaryMetric);
        if (!$latestProgress) {
            return 0.0;
        }

        $current = $latestProgress->getValue();
        $target = $primaryMetric->getTargetValue();
        $initial = $primaryMetric->getInitialValue();

        if ($target == $initial) {
            return 100.0;
        }

        return min(100.0, max(0.0, (($current - $initial) / ($target - $initial)) * 100));
    }

    /**
     * Obtient la dernière progression pour une métrique donnée
     */
    public function getLatestProgress(Metric $metric): ?Progress
    {
        $latestProgress = null;
        $latestDate = null;

        foreach ($this->progressEntries as $progress) {
            if ($progress->getMetric() === $metric) {
                if (!$latestDate || $progress->getDate() > $latestDate) {
                    $latestDate = $progress->getDate();
                    $latestProgress = $progress;
                }
            }
        }

        return $latestProgress;
    }
}
