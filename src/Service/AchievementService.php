<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Goal;
use App\Entity\Achievement;
use App\Entity\UserAchievement;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\GoalRepository;
use App\Repository\ProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\AchievementUnlockedEvent;

class AchievementService implements AchievementServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AchievementRepository $achievementRepository,
        private UserAchievementRepository $userAchievementRepository,
        private GoalRepository $goalRepository,
        private ProgressRepository $progressRepository,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Vérifie et débloque automatiquement les badges pour un utilisateur
     */
    public function checkAndUnlockAchievements(User $user): array
    {
        $unlockedAchievements = [];
        $availableAchievements = $this->achievementRepository->findAvailableForUser($user);

        foreach ($availableAchievements as $achievement) {
            if ($this->checkAchievementCriteria($user, $achievement)) {
                $userAchievement = $this->unlockAchievement($user, $achievement);
                $unlockedAchievements[] = $userAchievement;
            }
        }

        return $unlockedAchievements;
    }

    /**
     * Débloque manuellement un badge pour un utilisateur
     */
    public function unlockAchievement(User $user, Achievement $achievement, array $unlockData = []): UserAchievement
    {
        // Vérifier si l'utilisateur n'a pas déjà ce badge
        if ($this->userAchievementRepository->userHasAchievement($user, $achievement)) {
            throw new \InvalidArgumentException('L\'utilisateur possède déjà ce badge');
        }

        $userAchievement = new UserAchievement();
        $userAchievement->setUser($user);
        $userAchievement->setAchievement($achievement);
        $userAchievement->setUnlockData($unlockData);

        // Ajouter les points à l'utilisateur
        $user->addPoints($achievement->getPoints());

        $this->entityManager->persist($userAchievement);
        $this->entityManager->flush();

        // Dispatcher l'événement
        $event = new AchievementUnlockedEvent($user, $achievement, $userAchievement);
        $this->eventDispatcher->dispatch($event, AchievementUnlockedEvent::NAME);

        return $userAchievement;
    }

    /**
     * Vérifie si un utilisateur satisfait les critères d'un badge
     */
    public function checkAchievementCriteria(User $user, Achievement $achievement): bool
    {
        $criteria = $achievement->getCriteria();

        if (!$criteria || !isset($criteria['type'])) {
            return false;
        }

        return match ($criteria['type']) {
            'goal_created' => $this->checkGoalCreatedCriteria($user, $criteria),
            'progress_recorded' => $this->checkProgressRecordedCriteria($user, $criteria),
            'streak' => $this->checkStreakCriteria($user, $criteria),
            'goals_completed' => $this->checkGoalsCompletedCriteria($user, $criteria),
            'category_goal_completed' => $this->checkCategoryGoalCompletedCriteria($user, $criteria),
            'perfect_week' => $this->checkPerfectWeekCriteria($user, $criteria),
            'total_points' => $this->checkTotalPointsCriteria($user, $criteria),
            'session_duration' => $this->checkSessionDurationCriteria($user, $criteria),
            'consistency' => $this->checkConsistencyCriteria($user, $criteria),
            default => false
        };
    }

    /**
     * Calcule les recommandations de badges pour un utilisateur
     */
    public function getRecommendedAchievements(User $user, int $limit = 5): array
    {
        $available = $this->achievementRepository->findAvailableForUser($user);
        $recommendations = [];

        foreach ($available as $achievement) {
            $progress = $this->calculateAchievementProgress($user, $achievement);

            if ($progress['percentage'] > 0) {
                $recommendations[] = [
                    'achievement' => $achievement,
                    'progress' => $progress,
                    'priority' => $this->calculateRecommendationPriority($achievement, $progress)
                ];
            }
        }

        // Trier par priorité et limiter
        usort($recommendations, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Calcule la progression vers un badge
     */
    public function calculateAchievementProgress(User $user, Achievement $achievement): array
    {
        $criteria = $achievement->getCriteria();

        if (!$criteria || !isset($criteria['type'])) {
            return ['percentage' => 0, 'current' => 0, 'target' => 0];
        }

        return match ($criteria['type']) {
            'goal_created' => $this->calculateGoalCreatedProgress($user, $criteria),
            'progress_recorded' => $this->calculateProgressRecordedProgress($user, $criteria),
            'streak' => $this->calculateStreakProgress($user, $criteria),
            'goals_completed' => $this->calculateGoalsCompletedProgress($user, $criteria),
            'total_points' => $this->calculateTotalPointsProgress($user, $criteria),
            default => ['percentage' => 0, 'current' => 0, 'target' => 0]
        };
    }

    /**
     * Statistiques des badges pour un utilisateur
     */
    public function getUserAchievementStats(User $user): array
    {
        $userAchievements = $this->userAchievementRepository->findByUser($user);
        $allAchievements = $this->achievementRepository->findActive();

        $statsByLevel = [];
        $totalPoints = 0;

        foreach ($userAchievements as $userAchievement) {
            $achievement = $userAchievement->getAchievement();
            $level = $achievement->getLevel();

            if (!isset($statsByLevel[$level])) {
                $statsByLevel[$level] = 0;
            }
            $statsByLevel[$level]++;
            $totalPoints += $achievement->getPoints();
        }

        $recentAchievements = $this->userAchievementRepository->findRecentByUser($user, 30);

        return [
            'total_achievements' => count($userAchievements),
            'total_points' => $totalPoints,
            'completion_percentage' => (count($userAchievements) / count($allAchievements)) * 100,
            'stats_by_level' => $statsByLevel,
            'recent_achievements' => count($recentAchievements),
            'next_milestone' => $this->getNextMilestone($user),
            'rarest_achievement' => $this->getRarestAchievement($userAchievements)
        ];
    }

    /**
     * Classement des utilisateurs par badges
     */
    public function getLeaderboard(string $type = 'points', int $limit = 10): array
    {
        return match ($type) {
            'points' => $this->userAchievementRepository->getLeaderboard($limit),
            'achievements' => $this->getAchievementCountLeaderboard($limit),
            'rare' => $this->getRareAchievementLeaderboard($limit),
            default => []
        };
    }

    /**
     * Créer les badges par défaut du système
     */
    public function createDefaultAchievements(): void
    {
        $defaultAchievements = Achievement::getDefaultAchievements();

        foreach ($defaultAchievements as $achievementData) {
            $existing = $this->achievementRepository->findOneByCode($achievementData['code']);

            if (!$existing) {
                $achievement = new Achievement();
                $achievement->setName($achievementData['name']);
                $achievement->setCode($achievementData['code']);
                $achievement->setDescription($achievementData['description']);
                $achievement->setLevel($achievementData['level']);
                $achievement->setPoints($achievementData['points']);
                $achievement->setCriteria($achievementData['criteria']);
                $achievement->setIcon($achievementData['icon'] ?? null);
                $achievement->setCategoryCode($achievementData['categoryCode'] ?? null);
                $achievement->setIsSecret($achievementData['isSecret'] ?? false);

                $this->entityManager->persist($achievement);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Notifications de nouveaux badges
     */
    public function getUnnotifiedAchievements(User $user): array
    {
        return $this->userAchievementRepository->findUnnotifiedByUser($user);
    }

    /**
     * Marquer les badges comme notifiés
     */
    public function markAchievementsAsNotified(array $userAchievements): void
    {
        $this->userAchievementRepository->markAsNotified($userAchievements);
    }

    /**
     * Méthodes privées pour vérification des critères
     */
    private function checkGoalCreatedCriteria(User $user, array $criteria): bool
    {
        $goalCount = count($this->goalRepository->findBy(['user' => $user]));
        return $goalCount >= ($criteria['count'] ?? 1);
    }

    private function checkProgressRecordedCriteria(User $user, array $criteria): bool
    {
        $progressStats = $this->progressRepository->getProgressStats($user);
        return $progressStats['total_entries'] >= ($criteria['count'] ?? 1);
    }

    private function checkStreakCriteria(User $user, array $criteria): bool
    {
        return $user->getCurrentStreak() >= ($criteria['days'] ?? 7);
    }

    private function checkGoalsCompletedCriteria(User $user, array $criteria): bool
    {
        $completedGoals = $this->goalRepository->findByUserAndStatus($user, 'completed');
        return count($completedGoals) >= ($criteria['count'] ?? 1);
    }

    private function checkCategoryGoalCompletedCriteria(User $user, array $criteria): bool
    {
        $categoryCode = $criteria['category'] ?? null;
        if (!$categoryCode) return false;

        $completedGoals = $this->goalRepository->findByUserAndStatus($user, 'completed');
        $categoryCompletedCount = 0;

        foreach ($completedGoals as $goal) {
            if ($goal->getCategory()->getCode() === $categoryCode) {
                $categoryCompletedCount++;
            }
        }

        return $categoryCompletedCount >= ($criteria['count'] ?? 1);
    }

    private function checkPerfectWeekCriteria(User $user, array $criteria): bool
    {
        $weekStart = new \DateTime('monday this week');
        $weekProgress = $this->progressRepository->getWeekProgress($user, $weekStart);
        $activeGoals = $this->goalRepository->findActiveByUser($user);

        if (empty($activeGoals)) return false;

        // Vérifier que chaque jour de la semaine a des progressions
        $daysWithProgress = [];
        foreach ($weekProgress as $progress) {
            $day = $progress->getDate()->format('Y-m-d');
            $daysWithProgress[$day] = true;
        }

        return count($daysWithProgress) >= 7;
    }

    private function checkTotalPointsCriteria(User $user, array $criteria): bool
    {
        return $user->getTotalPoints() >= ($criteria['points'] ?? 100);
    }

    private function checkSessionDurationCriteria(User $user, array $criteria): bool
    {
        $sessionStats = $this->progressRepository->getProgressStats($user);
        return $sessionStats['total_duration'] >= ($criteria['duration'] ?? 3600);
    }

    private function checkConsistencyCriteria(User $user, array $criteria): bool
    {
        $days = $criteria['days'] ?? 30;
        $progressStats = $this->progressRepository->getProgressStats($user, $days);
        $requiredDays = $criteria['required_days'] ?? 20;

        return $progressStats['active_days'] >= $requiredDays;
    }

    /**
     * Méthodes de calcul de progression
     */
    private function calculateGoalCreatedProgress(User $user, array $criteria): array
    {
        $current = count($this->goalRepository->findBy(['user' => $user]));
        $target = $criteria['count'] ?? 1;

        return [
            'current' => $current,
            'target' => $target,
            'percentage' => min(100, ($current / $target) * 100)
        ];
    }

    private function calculateProgressRecordedProgress(User $user, array $criteria): array
    {
        $stats = $this->progressRepository->getProgressStats($user);
        $current = $stats['total_entries'];
        $target = $criteria['count'] ?? 1;

        return [
            'current' => $current,
            'target' => $target,
            'percentage' => min(100, ($current / $target) * 100)
        ];
    }

    private function calculateStreakProgress(User $user, array $criteria): array
    {
        $current = $user->getCurrentStreak();
        $target = $criteria['days'] ?? 7;

        return [
            'current' => $current,
            'target' => $target,
            'percentage' => min(100, ($current / $target) * 100)
        ];
    }

    private function calculateGoalsCompletedProgress(User $user, array $criteria): array
    {
        $completed = count($this->goalRepository->findByUserAndStatus($user, 'completed'));
        $target = $criteria['count'] ?? 1;

        return [
            'current' => $completed,
            'target' => $target,
            'percentage' => min(100, ($completed / $target) * 100)
        ];
    }

    private function calculateTotalPointsProgress(User $user, array $criteria): array
    {
        $current = $user->getTotalPoints();
        $target = $criteria['points'] ?? 100;

        return [
            'current' => $current,
            'target' => $target,
            'percentage' => min(100, ($current / $target) * 100)
        ];
    }

    private function calculateRecommendationPriority(Achievement $achievement, array $progress): int
    {
        $priority = 0;

        // Plus proche de l'objectif = priorité plus haute
        $priority += $progress['percentage'] * 0.5;

        // Badges de niveau inférieur = priorité plus haute
        $levelPriority = match ($achievement->getLevel()) {
            'bronze' => 50,
            'silver' => 40,
            'gold' => 30,
            'platinum' => 20,
            'diamond' => 10,
            default => 25
        };
        $priority += $levelPriority;

        // Moins de points = plus accessible
        $priority += max(0, 50 - ($achievement->getPoints() / 20));

        return (int) $priority;
    }

    private function getNextMilestone(User $user): ?array
    {
        $recommendations = $this->getRecommendedAchievements($user, 1);
        return !empty($recommendations) ? $recommendations[0] : null;
    }

    private function getRarestAchievement(array $userAchievements): ?Achievement
    {
        if (empty($userAchievements)) return null;

        $rarest = null;
        $minCount = PHP_INT_MAX;

        foreach ($userAchievements as $userAchievement) {
            $achievement = $userAchievement->getAchievement();
            $count = $achievement->getUnlockedCount();

            if ($count < $minCount) {
                $minCount = $count;
                $rarest = $achievement;
            }
        }

        return $rarest;
    }

    private function getAchievementCountLeaderboard(int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u.username, COUNT(ua.id) as achievement_count')
            ->from(User::class, 'u')
            ->leftJoin('u.userAchievements', 'ua')
            ->groupBy('u.id')
            ->orderBy('achievement_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function getRareAchievementLeaderboard(int $limit): array
    {
        // Utilisateurs avec les badges les plus rares
        return $this->entityManager->createQueryBuilder()
            ->select('u.username, COUNT(ua.id) as rare_count')
            ->from(User::class, 'u')
            ->leftJoin('u.userAchievements', 'ua')
            ->leftJoin('ua.achievement', 'a')
            ->where('a.isSecret = true OR (SELECT COUNT(ua2.id) FROM App\Entity\UserAchievement ua2 WHERE ua2.achievement = a.id) < 10')
            ->groupBy('u.id')
            ->orderBy('rare_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
