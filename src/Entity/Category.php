<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Ce code de catégorie existe déjà')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal', 'category', 'dashboard'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['goal', 'category', 'dashboard'])]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le nom doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['goal', 'category'])]
    #[Assert\NotBlank(message: 'Le code de la catégorie est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le code doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z_]+$/',
        message: 'Le code doit contenir uniquement des lettres majuscules et des underscores'
    )]
    private ?string $code = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['goal', 'category', 'dashboard'])]
    private ?string $icon = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['goal', 'category', 'dashboard'])]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format hexadécimal (#RRGGBB)'
    )]
    private ?string $color = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['category'])]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['category'])]
    private ?bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    #[Groups(['category'])]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'L\'ordre doit être entre {{ min }} et {{ max }}')]
    private ?int $displayOrder = 0;

    /**
     * @var Collection<int, Goal>
     * IMPORTANT: Ne pas inclure dans les groupes de sérialisation pour éviter les références circulaires
     */
    #[ORM\OneToMany(targetEntity: Goal::class, mappedBy: 'category')]
    #[MaxDepth(1)]
    private Collection $goals;

    public function __construct()
    {
        $this->goals = new ArrayCollection();
        $this->isActive = true;
        $this->displayOrder = 0;
    }

    // ... le reste de vos getters/setters reste identique

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    /**
     * @return Collection<int, Goal>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }

    public function addGoal(Goal $goal): static
    {
        if (!$this->goals->contains($goal)) {
            $this->goals->add($goal);
            $goal->setCategory($this);
        }
        return $this;
    }

    public function removeGoal(Goal $goal): static
    {
        if ($this->goals->removeElement($goal)) {
            if ($goal->getCategory() === $this) {
                $goal->setCategory(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le nombre d'objectifs actifs dans cette catégorie
     */
    #[Groups(['dashboard'])]
    public function getActiveGoalsCount(): int
    {
        return $this->goals->filter(fn(Goal $goal) => $goal->getStatus() === 'active')->count();
    }

    /**
     * Retourne le nombre d'objectifs complétés dans cette catégorie
     */
    #[Groups(['dashboard'])]
    public function getCompletedGoalsCount(): int
    {
        return $this->goals->filter(fn(Goal $goal) => $goal->getStatus() === 'completed')->count();
    }

    /**
     * Retourne le pourcentage moyen de completion des objectifs actifs
     */
    #[Groups(['dashboard'])]
    public function getAverageCompletionPercentage(): float
    {
        $activeGoals = $this->goals->filter(fn(Goal $goal) => $goal->getStatus() === 'active');

        if ($activeGoals->isEmpty()) {
            return 0.0;
        }

        $totalCompletion = 0.0;
        foreach ($activeGoals as $goal) {
            $totalCompletion += $goal->getCompletionPercentage();
        }

        return $totalCompletion / $activeGoals->count();
    }

    /**
     * Catégories prédéfinies populaires
     */
    public static function getDefaultCategories(): array
    {
        return [
            ['name' => 'Fitness', 'code' => 'FITNESS', 'icon' => '💪', 'color' => '#FF6B6B'],
            ['name' => 'Course à pied', 'code' => 'RUNNING', 'icon' => '🏃', 'color' => '#4ECDC4'],
            ['name' => 'Musculation', 'code' => 'WEIGHTLIFTING', 'icon' => '🏋️', 'color' => '#45B7D1'],
            ['name' => 'Nutrition', 'code' => 'NUTRITION', 'icon' => '🥗', 'color' => '#96CEB4'],
            ['name' => 'Lecture', 'code' => 'READING', 'icon' => '📚', 'color' => '#FECA57'],
            ['name' => 'Méditation', 'code' => 'MEDITATION', 'icon' => '🧘', 'color' => '#A55EEA'],
            ['name' => 'Sommeil', 'code' => 'SLEEP', 'icon' => '😴', 'color' => '#26C0B0'],
            ['name' => 'Hydratation', 'code' => 'HYDRATION', 'icon' => '💧', 'color' => '#3DC1D3'],
        ];
    }

    /**
     * Retourne une couleur par défaut si aucune n'est définie
     */
    public function getDisplayColor(): string
    {
        return $this->color ?? '#6C7CE7';
    }

    /**
     * Retourne une icône par défaut si aucune n'est définie
     */
    public function getDisplayIcon(): string
    {
        return $this->icon ?? '🎯';
    }
}
