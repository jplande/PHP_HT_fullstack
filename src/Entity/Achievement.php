<?php

namespace App\Entity;

use App\Repository\AchievementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: AchievementRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Ce code de badge existe déjà')]
class Achievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['achievement', 'user_achievement'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['achievement', 'user_achievement', 'dashboard'])]
    #[Assert\NotBlank(message: 'Le nom du badge est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['achievement'])]
    #[Assert\NotBlank(message: 'Le code du badge est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[A-Z_]+$/',
        message: 'Le code doit contenir uniquement des lettres majuscules et des underscores'
    )]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['achievement', 'user_achievement'])]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'La description doit faire au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['achievement', 'user_achievement', 'dashboard'])]
    private ?string $icon = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['achievement', 'user_achievement'])]
    #[Assert\Range(min: 1, max: 10000, notInRangeMessage: 'Les points doivent être entre {{ min }} et {{ max }}')]
    private ?int $points = 0;

    #[ORM\Column(type: 'json')]
    #[Groups(['achievement'])]
    #[Assert\NotNull(message: 'Les critères sont obligatoires')]
    private ?array $criteria = null;

    #[ORM\Column(length: 20)]
    #[Groups(['achievement'])]
    #[Assert\Choice(
        choices: ['bronze', 'silver', 'gold', 'platinum', 'diamond'],
        message: 'Le niveau doit être bronze, silver, gold, platinum ou diamond'
    )]
    private ?string $level = 'bronze';

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['achievement'])]
    private ?string $categoryCode = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['achievement'])]
    private ?bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['achievement'])]
    private ?bool $isSecret = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['achievement'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'achievement', cascade: ['persist', 'remove'])]
    private Collection $userAchievements;

    public function __construct()
    {
        $this->userAchievements = new ArrayCollection();
        $this->isActive = true;
        $this->isSecret = false;
        $this->level = 'bronze';
        $this->points = 0;
        $this->createdAt = new \DateTime();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    public function setCriteria(array $criteria): static
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getCategoryCode(): ?string
    {
        return $this->categoryCode;
    }

    public function setCategoryCode(?string $categoryCode): static
    {
        $this->categoryCode = $categoryCode;
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

    public function getIsSecret(): ?bool
    {
        return $this->isSecret;
    }

    public function setIsSecret(bool $isSecret): static
    {
        $this->isSecret = $isSecret;
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

    /**
     * @return Collection<int, UserAchievement>
     */
    public function getUserAchievements(): Collection
    {
        return $this->userAchievements;
    }

    public function addUserAchievement(UserAchievement $userAchievement): static
    {
        if (!$this->userAchievements->contains($userAchievement)) {
            $this->userAchievements->add($userAchievement);
            $userAchievement->setAchievement($this);
        }
        return $this;
    }

    public function removeUserAchievement(UserAchievement $userAchievement): static
    {
        if ($this->userAchievements->removeElement($userAchievement)) {
            if ($userAchievement->getAchievement() === $this) {
                $userAchievement->setAchievement(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le nombre d'utilisateurs ayant obtenu ce badge
     */
    #[Groups(['achievement'])]
    public function getUnlockedCount(): int
    {
        return $this->userAchievements->count();
    }

    /**
     * Vérifie si un utilisateur a débloqué ce badge
     */
    public function isUnlockedByUser(User $user): bool
    {
        foreach ($this->userAchievements as $userAchievement) {
            if ($userAchievement->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne la couleur associée au niveau
     */
    #[Groups(['achievement', 'user_achievement', 'dashboard'])]
    public function getLevelColor(): string
    {
        return match ($this->level) {
            'bronze' => '#CD7F32',
            'silver' => '#C0C0C0',
            'gold' => '#FFD700',
            'platinum' => '#E5E4E2',
            'diamond' => '#B9F2FF',
            default => '#6C7CE7',
        };
    }

    /**
     * Retourne l'icône par défaut selon le niveau
     */
    public function getDisplayIcon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match ($this->level) {
            'bronze' => '🥉',
            'silver' => '🥈',
            'gold' => '🥇',
            'platinum' => '💎',
            'diamond' => '💠',
            default => '🏆',
        };
    }

    /**
     * Badges prédéfinis du système
     */
    public static function getDefaultAchievements(): array
    {
        return [
            // Badges de début
            [
                'name' => 'Premier pas',
                'code' => 'FIRST_GOAL',
                'description' => 'Créer votre premier objectif',
                'level' => 'bronze',
                'points' => 10,
                'criteria' => ['type' => 'goal_created', 'count' => 1],
                'icon' => '🎯'
            ],
            [
                'name' => 'Débutant motivé',
                'code' => 'FIRST_PROGRESS',
                'description' => 'Enregistrer votre première progression',
                'level' => 'bronze',
                'points' => 15,
                'criteria' => ['type' => 'progress_recorded', 'count' => 1],
                'icon' => '📊'
            ],

            // Badges de régularité
            [
                'name' => 'Régularité',
                'code' => 'STREAK_7',
                'description' => 'Maintenir une série de 7 jours consécutifs',
                'level' => 'silver',
                'points' => 50,
                'criteria' => ['type' => 'streak', 'days' => 7],
                'icon' => '🔥'
            ],
            [
                'name' => 'Persévérant',
                'code' => 'STREAK_30',
                'description' => 'Maintenir une série de 30 jours consécutifs',
                'level' => 'gold',
                'points' => 200,
                'criteria' => ['type' => 'streak', 'days' => 30],
                'icon' => '⚡'
            ],

            // Badges d'objectifs
            [
                'name' => 'Finisseur',
                'code' => 'FIRST_GOAL_COMPLETED',
                'description' => 'Compléter votre premier objectif',
                'level' => 'silver',
                'points' => 100,
                'criteria' => ['type' => 'goals_completed', 'count' => 1],
                'icon' => '✅'
            ],
            [
                'name' => 'Collectionneur',
                'code' => 'GOALS_COMPLETED_10',
                'description' => 'Compléter 10 objectifs',
                'level' => 'gold',
                'points' => 500,
                'criteria' => ['type' => 'goals_completed', 'count' => 10],
                'icon' => '🏆'
            ],

            // Badges spécialisés par catégorie
            [
                'name' => 'Athlète débutant',
                'code' => 'FITNESS_BEGINNER',
                'description' => 'Compléter un objectif de fitness',
                'level' => 'bronze',
                'points' => 25,
                'criteria' => ['type' => 'category_goal_completed', 'category' => 'FITNESS', 'count' => 1],
                'categoryCode' => 'FITNESS',
                'icon' => '💪'
            ],
            [
                'name' => 'Coureur confirmé',
                'code' => 'RUNNING_ADVANCED',
                'description' => 'Compléter 5 objectifs de course',
                'level' => 'gold',
                'points' => 300,
                'criteria' => ['type' => 'category_goal_completed', 'category' => 'RUNNING', 'count' => 5],
                'categoryCode' => 'RUNNING',
                'icon' => '🏃'
            ],

            // Badges secrets
            [
                'name' => 'Perfectionniste',
                'code' => 'PERFECT_WEEK',
                'description' => 'Atteindre tous vos objectifs pendant une semaine complète',
                'level' => 'platinum',
                'points' => 1000,
                'criteria' => ['type' => 'perfect_week'],
                'isSecret' => true,
                'icon' => '🌟'
            ]
        ];
    }

    /**
     * Valide la structure des critères
     */
    #[Assert\Callback]
    public function validateCriteria(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (!$this->criteria || !is_array($this->criteria)) {
            $context->buildViolation('Les critères doivent être un tableau valide')
                ->atPath('criteria')
                ->addViolation();
            return;
        }

        if (!isset($this->criteria['type'])) {
            $context->buildViolation('Les critères doivent contenir un type')
                ->atPath('criteria')
                ->addViolation();
        }

        $validTypes = [
            'goal_created', 'progress_recorded', 'streak', 'goals_completed',
            'category_goal_completed', 'perfect_week', 'total_points'
        ];

        if (isset($this->criteria['type']) && !in_array($this->criteria['type'], $validTypes)) {
            $context->buildViolation('Type de critère invalide')
                ->atPath('criteria')
                ->addViolation();
        }
    }
}
