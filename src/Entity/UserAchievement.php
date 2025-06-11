<?php

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\Index(name: 'idx_user_achievement_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_user_achievement_unlocked_at', columns: ['unlocked_at'])]
#[UniqueEntity(
    fields: ['user', 'achievement'],
    message: 'Cet utilisateur possède déjà ce badge'
)]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_achievement'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user_achievement', 'dashboard'])]
    private ?\DateTimeInterface $unlockedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user_achievement'])]
    private ?array $unlockData = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user_achievement'])]
    private ?bool $isNotified = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Achievement::class, inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_achievement', 'dashboard'])]
    private ?Achievement $achievement = null;

    public function __construct()
    {
        $this->unlockedAt = new \DateTime();
        $this->isNotified = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnlockedAt(): ?\DateTimeInterface
    {
        return $this->unlockedAt;
    }

    public function setUnlockedAt(\DateTimeInterface $unlockedAt): static
    {
        $this->unlockedAt = $unlockedAt;
        return $this;
    }

    public function getUnlockData(): ?array
    {
        return $this->unlockData;
    }

    public function setUnlockData(?array $unlockData): static
    {
        $this->unlockData = $unlockData;
        return $this;
    }

    public function getIsNotified(): ?bool
    {
        return $this->isNotified;
    }

    public function setIsNotified(bool $isNotified): static
    {
        $this->isNotified = $isNotified;
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

    public function getAchievement(): ?Achievement
    {
        return $this->achievement;
    }

    public function setAchievement(?Achievement $achievement): static
    {
        $this->achievement = $achievement;
        return $this;
    }

    /**
     * Ajoute des données de déverrouillage
     */
    public function addUnlockData(string $key, mixed $value): static
    {
        $data = $this->unlockData ?? [];
        $data[$key] = $value;
        $this->unlockData = $data;
        return $this;
    }

    /**
     * Récupère une donnée de déverrouillage
     */
    public function getUnlockDataValue(string $key): mixed
    {
        return $this->unlockData[$key] ?? null;
    }

    /**
     * Retourne le nombre de jours depuis le déverrouillage
     */
    #[Groups(['user_achievement'])]
    public function getDaysSinceUnlock(): int
    {
        if (!$this->unlockedAt) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->unlockedAt);
        return $diff->days;
    }

    /**
     * Vérifie si le badge a été déverrouillé récemment (moins de 7 jours)
     */
    #[Groups(['user_achievement'])]
    public function isRecentlyUnlocked(): bool
    {
        return $this->getDaysSinceUnlock() <= 7;
    }
}
