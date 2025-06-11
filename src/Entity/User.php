<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Traits\StatisticsPropertiesTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use StatisticsPropertiesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user', 'goal', 'dashboard'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user', 'dashboard'])]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['user', 'dashboard'])]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['user', 'dashboard'])]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['user'])]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['user', 'dashboard'])]
    private ?int $level = 1;

    #[ORM\Column(type: 'integer')]
    #[Groups(['user', 'dashboard'])]
    private ?int $totalPoints = 0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['user', 'dashboard'])]
    private ?int $currentStreak = 0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['user', 'dashboard'])]
    private ?int $longestStreak = 0;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['user'])]
    private ?\DateTimeInterface $lastActivityDate = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['user'])]
    #[Assert\Choice(choices: ['metric', 'imperial'], message: 'Le système d\'unités doit être metric ou imperial')]
    private ?string $unitSystem = 'metric';

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['user'])]
    #[Assert\Locale(message: 'La langue n\'est pas valide')]
    private ?string $locale = 'fr';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user'])]
    private ?array $preferences = null;

    // SUPPRIMÉ : createdAt, updatedAt, status (viennent du trait)

    /**
     * @var Collection<int, Goal>
     */
    #[ORM\OneToMany(targetEntity: Goal::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $goals;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[Groups(['dashboard'])]
    private Collection $userAchievements;

    public function __construct()
    {
        $this->goals = new ArrayCollection();
        $this->userAchievements = new ArrayCollection();
        $this->level = 1;
        $this->totalPoints = 0;
        $this->currentStreak = 0;
        $this->longestStreak = 0;
        $this->unitSystem = 'metric';
        $this->locale = 'fr';

        // IMPORTANT : Initialiser le status depuis le trait
        $this->setStatus('active');

        // Les dates sont gérées par le trait via @PrePersist
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;
        return $this;
    }

    public function getCurrentStreak(): ?int
    {
        return $this->currentStreak;
    }

    public function setCurrentStreak(int $currentStreak): static
    {
        $this->currentStreak = $currentStreak;
        $this->longestStreak = max($this->longestStreak, $currentStreak);
        return $this;
    }

    public function getLongestStreak(): ?int
    {
        return $this->longestStreak;
    }

    public function setLongestStreak(int $longestStreak): static
    {
        $this->longestStreak = $longestStreak;
        return $this;
    }

    public function getLastActivityDate(): ?\DateTimeInterface
    {
        return $this->lastActivityDate;
    }

    public function setLastActivityDate(?\DateTimeInterface $lastActivityDate): static
    {
        $this->lastActivityDate = $lastActivityDate;
        return $this;
    }

    public function getUnitSystem(): ?string
    {
        return $this->unitSystem;
    }

    public function setUnitSystem(?string $unitSystem): static
    {
        $this->unitSystem = $unitSystem;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): static
    {
        $this->preferences = $preferences;
        return $this;
    }

    // SUPPRIMÉ : getCreatedAt, setCreatedAt, getUpdatedAt, setUpdatedAt (viennent du trait)
    // SUPPRIMÉ : getStatus, setStatus (viennent du trait)

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
            $goal->setUser($this);
        }
        return $this;
    }

    public function removeGoal(Goal $goal): static
    {
        if ($this->goals->removeElement($goal)) {
            if ($goal->getUser() === $this) {
                $goal->setUser(null);
            }
        }
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
            $userAchievement->setUser($this);
        }
        return $this;
    }

    public function removeUserAchievement(UserAchievement $userAchievement): static
    {
        if ($this->userAchievements->removeElement($userAchievement)) {
            if ($userAchievement->getUser() === $this) {
                $userAchievement->setUser(null);
            }
        }
        return $this;
    }

    // SUPPRIMÉ : @PreUpdate car déjà dans le trait

    /**
     * Retourne le nom complet de l'utilisateur
     */
    #[Groups(['user', 'dashboard'])]
    public function getFullName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);
        return empty($parts) ? $this->username : implode(' ', $parts);
    }

    /**
     * Ajoute des points et recalcule le niveau
     */
    public function addPoints(int $points): static
    {
        $this->totalPoints += $points;
        $this->updateLevel();
        return $this;
    }

    /**
     * Met à jour le niveau basé sur les points totaux
     */
    private function updateLevel(): void
    {
        // Formule: niveau = 1 + floor(sqrt(points / 100))
        $newLevel = 1 + floor(sqrt($this->totalPoints / 100));
        $this->level = (int) $newLevel;
    }

    /**
     * Points nécessaires pour le prochain niveau
     */
    #[Groups(['user', 'dashboard'])]
    public function getPointsToNextLevel(): int
    {
        $nextLevel = $this->level + 1;
        $pointsForNextLevel = ($nextLevel - 1) * ($nextLevel - 1) * 100;
        return max(0, $pointsForNextLevel - $this->totalPoints);
    }

    /**
     * Pourcentage de progression vers le prochain niveau
     */
    #[Groups(['user', 'dashboard'])]
    public function getLevelProgressPercentage(): float
    {
        $currentLevelPoints = ($this->level - 1) * ($this->level - 1) * 100;
        $nextLevelPoints = $this->level * $this->level * 100;

        if ($nextLevelPoints == $currentLevelPoints) {
            return 100.0;
        }

        $progress = ($this->totalPoints - $currentLevelPoints) / ($nextLevelPoints - $currentLevelPoints);
        return min(100.0, max(0.0, $progress * 100));
    }

    /**
     * Met à jour la série de jours consécutifs
     */
    public function updateStreak(): static
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if (!$this->lastActivityDate) {
            $this->currentStreak = 1;
            $this->lastActivityDate = $today;
        } else {
            $lastActivity = clone $this->lastActivityDate;
            $lastActivity->setTime(0, 0, 0);
            $daysDiff = $today->diff($lastActivity)->days;

            if ($daysDiff === 1) {
                // Jour consécutif
                $this->currentStreak++;
                $this->lastActivityDate = $today;
            } elseif ($daysDiff === 0) {
                // Même jour, pas de changement
                return $this;
            } else {
                // Série cassée
                $this->currentStreak = 1;
                $this->lastActivityDate = $today;
            }
        }

        $this->longestStreak = max($this->longestStreak, $this->currentStreak);
        return $this;
    }

    /**
     * Retourne les objectifs actifs
     */
    public function getActiveGoals(): Collection
    {
        return $this->goals->filter(fn(Goal $goal) => $goal->getStatus() === 'active');
    }

    /**
     * Retourne les objectifs complétés
     */
    public function getCompletedGoals(): Collection
    {
        return $this->goals->filter(fn(Goal $goal) => $goal->getStatus() === 'completed');
    }

    /**
     * Nombre total d'objectifs complétés
     */
    #[Groups(['user', 'dashboard'])]
    public function getCompletedGoalsCount(): int
    {
        return $this->getCompletedGoals()->count();
    }

    /**
     * Pourcentage moyen de completion des objectifs actifs
     */
    #[Groups(['user', 'dashboard'])]
    public function getAverageGoalCompletion(): float
    {
        $activeGoals = $this->getActiveGoals();

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
     * Retourne les badges récents (derniers 30 jours)
     */
    public function getRecentAchievements(): Collection
    {
        $thirtyDaysAgo = new \DateTime('-30 days');

        return $this->userAchievements->filter(
            fn(UserAchievement $ua) => $ua->getUnlockedAt() >= $thirtyDaysAgo
        );
    }

    /**
     * Vérifie si l'utilisateur a un badge spécifique
     */
    public function hasAchievement(string $achievementCode): bool
    {
        foreach ($this->userAchievements as $userAchievement) {
            if ($userAchievement->getAchievement()->getCode() === $achievementCode) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ajoute une préférence utilisateur
     */
    public function setPreference(string $key, mixed $value): static
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * Récupère une préférence utilisateur
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Vérifie si l'utilisateur est actif (activité dans les 7 derniers jours)
     */
    #[Groups(['dashboard'])]
    public function isActive(): bool
    {
        if (!$this->lastActivityDate) {
            return false;
        }

        $sevenDaysAgo = new \DateTime('-7 days');
        return $this->lastActivityDate >= $sevenDaysAgo;
    }

    /**
     * Retourne le rang de l'utilisateur selon son niveau
     */
    #[Groups(['user', 'dashboard'])]
    public function getRank(): string
    {
        return match (true) {
            $this->level >= 50 => 'Légende',
            $this->level >= 30 => 'Expert',
            $this->level >= 20 => 'Avancé',
            $this->level >= 10 => 'Confirmé',
            $this->level >= 5 => 'Intermédiaire',
            default => 'Débutant',
        };
    }

    /**
     * Statistiques globales de l'utilisateur
     */
    #[Groups(['dashboard'])]
    public function getStats(): array
    {
        return [
            'totalGoals' => $this->goals->count(),
            'activeGoals' => $this->getActiveGoals()->count(),
            'completedGoals' => $this->getCompletedGoalsCount(),
            'totalAchievements' => $this->userAchievements->count(),
            'recentAchievements' => $this->getRecentAchievements()->count(),
            'averageCompletion' => $this->getAverageGoalCompletion(),
            'daysActive' => $this->getCreatedAt() ? (new \DateTime())->diff($this->getCreatedAt())->days : 0,
        ];
    }

    /**
     * Préférences par défaut pour un nouvel utilisateur
     */
    public static function getDefaultPreferences(): array
    {
        return [
            'notifications' => [
                'dailyReminder' => true,
                'goalDeadline' => true,
                'achievements' => true,
                'weeklyReport' => true,
            ],
            'privacy' => [
                'profilePublic' => false,
                'statsPublic' => false,
            ],
            'display' => [
                'theme' => 'auto',
                'chartType' => 'line',
                'showAnimations' => true,
            ],
            'goals' => [
                'defaultFrequency' => 'daily',
                'autoArchive' => true,
                'reminderTime' => '20:00',
            ],
        ];
    }
}
