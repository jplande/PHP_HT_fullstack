<?php

namespace App\Service;

use App\Entity\Goal;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Metric;
use App\Repository\GoalRepository;
use App\Repository\CategoryRepository;
use App\Repository\MetricRepository;
use App\Repository\ProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
// AJOUTÉ: Import des événements manquants
use App\Event\GoalCreatedEvent;
use App\Event\GoalCompletedEvent;

class GoalService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GoalRepository $goalRepository,
        private CategoryRepository $categoryRepository,
        private MetricRepository $metricRepository,
        private ProgressRepository $progressRepository,
        private EventDispatcherInterface $eventDispatcher,
        // AJOUTÉ: Rendre le service optionnel avec une interface pour éviter la dépendance circulaire
        private ?AchievementServiceInterface $achievementService = null
    ) {}

    /**
     * Crée un nouvel objectif avec ses métriques
     */
    public function createGoal(User $user, array $goalData, array $metricsData = []): Goal
    {
        $goal = new Goal();
        $goal->setUser($user);
        $goal->setTitle($goalData['title']);
        $goal->setDescription($goalData['description'] ?? null);
        $goal->setFrequencyType($goalData['frequencyType']);
        $goal->setStartDate($goalData['startDate']);
        $goal->setEndDate($goalData['endDate'] ?? null);

        // Associer la catégorie
        if (isset($goalData['categoryId'])) {
            $category = $this->categoryRepository->find($goalData['categoryId']);
            if (!$category) {
                throw new \InvalidArgumentException('Catégorie non trouvée');
            }
            $goal->setCategory($category);
        }

        $this->entityManager->persist($goal);

        // Créer les métriques
        foreach ($metricsData as $index => $metricData) {
            $metric = $this->createMetricForGoal($goal, $metricData, $index);
            $goal->addMetric($metric);
        }

        $this->entityManager->flush();

        // Dispatcher l'événement si la classe existe
        if (class_exists(GoalCreatedEvent::class)) {
            $event = new GoalCreatedEvent($goal);
            $this->eventDispatcher->dispatch($event, GoalCreatedEvent::NAME);
        }

        // Vérifier les badges si le service existe
        if ($this->achievementService) {
            $this->achievementService->checkAndUnlockAchievements($user);
        }

        return $goal;
    }

    /**
     * Met à jour un objectif
     */
    public function updateGoal(Goal $goal, array $goalData): Goal
    {
        if (isset($goalData['title'])) {
            $goal->setTitle($goalData['title']);
        }

        if (isset($goalData['description'])) {
            $goal->setDescription($goalData['description']);
        }

        if (isset($goalData['frequencyType'])) {
            $goal->setFrequencyType($goalData['frequencyType']);
        }

        if (isset($goalData['endDate'])) {
            $endDate = $goalData['endDate'];
            if (is_string($endDate)) {
                $endDate = new \DateTime($endDate);
            }
            $goal->setEndDate($endDate);
        }

        if (isset($goalData['status'])) {
            $oldStatus = $goal->getStatus();
            $goal->setStatus($goalData['status']);

            // Si l'objectif devient "completed", déclencher l'événement
            if ($oldStatus !== 'completed' && $goalData['status'] === 'completed') {
                $this->handleGoalCompletion($goal);
            }
        }

        $this->entityManager->flush();

        return $goal;
    }

    /**
     * Supprime ou archive un objectif
     */
    public function deleteGoal(Goal $goal, bool $hardDelete = false): void
    {
        if ($hardDelete) {
            $this->entityManager->remove($goal);
        } else {
            $goal->setStatus('archived');
            $this->entityManager->persist($goal);
        }

        $this->entityManager->flush();
    }

    /**
     * Duplique un objectif
     */
    public function duplicateGoal(Goal $originalGoal, User $user = null): Goal
    {
        $targetUser = $user ?? $originalGoal->getUser();

        $newGoal = new Goal();
        $newGoal->setUser($targetUser);
        $newGoal->setTitle($originalGoal->getTitle() . ' (copie)');
        $newGoal->setDescription($originalGoal->getDescription());
        $newGoal->setFrequencyType($originalGoal->getFrequencyType());
        $newGoal->setCategory($originalGoal->getCategory());
        $newGoal->setStartDate(new \DateTime());

        $this->entityManager->persist($newGoal);

        // Dupliquer les métriques
        foreach ($originalGoal->getMetrics() as $originalMetric) {
            $newMetric = new Metric();
            $newMetric->setGoal($newGoal);
            $newMetric->setName($originalMetric->getName());
            $newMetric->setUnit($originalMetric->getUnit());
            $newMetric->setEvolutionType($originalMetric->getEvolutionType());
            $newMetric->setInitialValue($originalMetric->getInitialValue());
            $newMetric->setTargetValue($originalMetric->getTargetValue());
            $newMetric->setIsPrimary($originalMetric->getIsPrimary());

            // CORRIGÉ: Vérifier si les méthodes existent avant de les appeler
            if (method_exists($originalMetric, 'getColor')) {
                $newMetric->setColor($originalMetric->getColor());
            }
            if (method_exists($originalMetric, 'getDisplayOrder')) {
                $newMetric->setDisplayOrder($originalMetric->getDisplayOrder());
            }

            $this->entityManager->persist($newMetric);
            $newGoal->addMetric($newMetric);
        }

        $this->entityManager->flush();

        return $newGoal;
    }

    /**
     * Calcule les statistiques d'un objectif
     */
    public function getGoalStatistics(Goal $goal): array
    {
        // CORRIGÉ: Utiliser des méthodes qui existent
        $metrics = $goal->getMetrics()->toArray();
        $progressEntries = $goal->getProgressEntries()->toArray();

        $totalProgress = 0;
        $completedMetrics = 0;
        $totalMetrics = count($metrics);

        foreach ($metrics as $metric) {
            $completion = $this->calculateMetricCompletion($metric);
            $totalProgress += $completion;

            if ($completion >= 100) {
                $completedMetrics++;
            }
        }

        $overallCompletion = $totalMetrics > 0 ? $totalProgress / $totalMetrics : 0;

        return [
            'overall_completion' => $overallCompletion,
            'completed_metrics' => $completedMetrics,
            'total_metrics' => $totalMetrics,
            'total_progress_entries' => count($progressEntries),
            'latest_progress_date' => $this->getLatestProgressDate($progressEntries),
            'days_since_last_progress' => $this->getDaysSinceLastProgress($progressEntries),
        ];
    }

    /**
     * Recommande des objectifs similaires
     */
    public function getRecommendedGoals(User $user, int $limit = 5): array
    {
        $userGoals = $this->goalRepository->findActiveByUser($user);

        if (empty($userGoals)) {
            // Nouveau utilisateur : recommander des objectifs populaires
            return $this->getPopularGoalTemplates($limit);
        }

        // Analyser les catégories préférées
        $categories = [];
        foreach ($userGoals as $goal) {
            $category = $goal->getCategory();
            if ($category) {
                $categoryCode = method_exists($category, 'getCode') ? $category->getCode() : $category->getName();
                $categories[$categoryCode] = ($categories[$categoryCode] ?? 0) + 1;
            }
        }

        // Recommander des objectifs dans les catégories préférées
        arsort($categories);
        $topCategories = array_slice(array_keys($categories), 0, 3);

        $recommendations = [];
        foreach ($topCategories as $categoryCode) {
            $category = method_exists($this->categoryRepository, 'findOneByCode')
                ? $this->categoryRepository->findOneByCode($categoryCode)
                : $this->categoryRepository->findOneBy(['name' => $categoryCode]);

            if ($category) {
                $recommendations[] = $this->generateGoalRecommendation($category, $user);
            }
        }

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Vérifie si un objectif doit être marqué comme complété automatiquement
     */
    public function checkGoalAutoCompletion(Goal $goal): bool
    {
        $allMetricsCompleted = true;

        foreach ($goal->getMetrics() as $metric) {
            if ($this->calculateMetricCompletion($metric) < 100) {
                $allMetricsCompleted = false;
                break;
            }
        }

        if ($allMetricsCompleted && $goal->getStatus() === 'active') {
            $goal->setStatus('completed');
            $this->entityManager->flush();

            $this->handleGoalCompletion($goal);
            return true;
        }

        return false;
    }

    /**
     * Génère un rapport de progression pour un objectif
     */
    public function generateProgressReport(Goal $goal, int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();

        $progressData = $this->progressRepository->findByGoalAndPeriod($goal, $startDate, $endDate);
        $statistics = $this->getGoalStatistics($goal);

        // Analyser la fréquence de progression
        $progressByDate = [];
        foreach ($progressData as $progress) {
            $date = $progress->getDate()->format('Y-m-d');
            $progressByDate[$date] = ($progressByDate[$date] ?? 0) + 1;
        }

        $averageProgressPerDay = count($progressByDate) > 0 ?
            array_sum($progressByDate) / count($progressByDate) : 0;

        // Analyser la régularité
        $expectedDays = $this->calculateExpectedProgressDays($goal, $days);
        $actualDays = count($progressByDate);
        $consistencyRate = $expectedDays > 0 ? ($actualDays / $expectedDays) * 100 : 0;

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_progress_entries' => count($progressData),
            'active_days' => count($progressByDate),
            'average_progress_per_day' => $averageProgressPerDay,
            'consistency_rate' => $consistencyRate,
            'completion_trend' => $this->calculateCompletionTrend($goal, $days),
            'statistics' => $statistics,
            'recommendations' => $this->generateProgressRecommendations($goal, $consistencyRate)
        ];
    }

    /**
     * Archive automatiquement les anciens objectifs
     */
    public function archiveOldGoals(int $daysInactive = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysInactive} days");

        // CORRIGÉ: Utiliser une méthode qui existe ou créer une requête personnalisée
        $qb = $this->goalRepository->createQueryBuilder('g')
            ->where('g.status = :status')
            ->andWhere('g.updatedAt < :cutoffDate')
            ->setParameter('status', 'active')
            ->setParameter('cutoffDate', $cutoffDate);

        $goals = $qb->getQuery()->getResult();

        $archivedCount = 0;
        foreach ($goals as $goal) {
            $goal->setStatus('archived');
            $this->entityManager->persist($goal);
            $archivedCount++;
        }

        if ($archivedCount > 0) {
            $this->entityManager->flush();
        }

        return $archivedCount;
    }

    /**
     * Méthodes privées
     */
    private function createMetricForGoal(Goal $goal, array $metricData, int $order): Metric
    {
        $metric = new Metric();
        $metric->setGoal($goal);
        $metric->setName($metricData['name']);
        $metric->setUnit($metricData['unit']);
        $metric->setEvolutionType($metricData['evolutionType']);
        $metric->setInitialValue($metricData['initialValue']);
        $metric->setTargetValue($metricData['targetValue']);
        $metric->setIsPrimary($metricData['isPrimary'] ?? false);

        // Propriétés optionnelles qui peuvent ne pas exister
        if (isset($metricData['color']) && method_exists($metric, 'setColor')) {
            $metric->setColor($metricData['color']);
        }
        if (method_exists($metric, 'setDisplayOrder')) {
            $metric->setDisplayOrder($order);
        }

        $this->entityManager->persist($metric);

        return $metric;
    }

    private function handleGoalCompletion(Goal $goal): void
    {
        // Dispatcher l'événement de completion si la classe existe
        if (class_exists(GoalCompletedEvent::class)) {
            $event = new GoalCompletedEvent($goal);
            $this->eventDispatcher->dispatch($event, GoalCompletedEvent::NAME);
        }

        // Vérifier les badges si le service existe
        if ($this->achievementService) {
            $this->achievementService->checkAndUnlockAchievements($goal->getUser());
        }
    }

    private function calculateMetricCompletion(Metric $metric): float
    {
        // Logique simplifiée - à adapter selon votre entité Metric
        $initialValue = $metric->getInitialValue();
        $targetValue = $metric->getTargetValue();

        // Vous devrez adapter cette méthode selon votre logique métier
        $currentValue = $initialValue; // À récupérer depuis la dernière progression

        if ($targetValue == $initialValue) {
            return 100.0;
        }

        return min(100.0, max(0.0, (($currentValue - $initialValue) / ($targetValue - $initialValue)) * 100));
    }

    private function getLatestProgressDate(array $progressEntries): ?\DateTime
    {
        if (empty($progressEntries)) {
            return null;
        }

        $latestDate = null;
        foreach ($progressEntries as $progress) {
            $date = $progress->getDate();
            if (!$latestDate || $date > $latestDate) {
                $latestDate = $date;
            }
        }

        return $latestDate;
    }

    private function getDaysSinceLastProgress(array $progressEntries): int
    {
        $latestDate = $this->getLatestProgressDate($progressEntries);

        if (!$latestDate) {
            return 999; // Aucune progression
        }

        return (new \DateTime())->diff($latestDate)->days;
    }

    private function getPopularGoalTemplates(int $limit): array
    {
        return [
            [
                'title' => 'Faire 30 pompes par jour',
                'category' => 'FITNESS',
                'frequency' => 'daily',
                'metrics' => [
                    ['name' => 'Pompes', 'unit' => 'répétitions', 'evolutionType' => 'increase', 'target' => 30]
                ]
            ],
            [
                'title' => 'Courir 5 km sans s\'arrêter',
                'category' => 'RUNNING',
                'frequency' => 'weekly',
                'metrics' => [
                    ['name' => 'Distance', 'unit' => 'km', 'evolutionType' => 'increase', 'target' => 5]
                ]
            ],
            [
                'title' => 'Lire 15 minutes par jour',
                'category' => 'READING',
                'frequency' => 'daily',
                'metrics' => [
                    ['name' => 'Temps de lecture', 'unit' => 'minutes', 'evolutionType' => 'maintain', 'target' => 15]
                ]
            ]
        ];
    }

    private function generateGoalRecommendation(Category $category, User $user): array
    {
        $categoryName = method_exists($category, 'getCode') ? $category->getCode() : $category->getName();

        $templates = [
            'FITNESS' => [
                'Améliorer son cardio',
                'Renforcer ses abdominaux',
                'Développer sa force'
            ],
            'RUNNING' => [
                'Courir plus longtemps',
                'Améliorer sa vitesse',
                'Préparer une course'
            ],
            'NUTRITION' => [
                'Manger plus de légumes',
                'Réduire le sucre',
                'Boire plus d\'eau'
            ]
        ];

        $goalTitles = $templates[$categoryName] ?? ['Nouvel objectif ' . $categoryName];
        $randomTitle = $goalTitles[array_rand($goalTitles)];

        return [
            'title' => $randomTitle,
            'category' => $category,
            'reason' => 'Basé sur vos objectifs précédents en ' . $categoryName
        ];
    }

    private function calculateExpectedProgressDays(Goal $goal, int $days): int
    {
        return match ($goal->getFrequencyType()) {
            'daily' => $days,
            'weekly' => ceil($days / 7),
            'monthly' => ceil($days / 30),
            default => $days
        };
    }

    private function calculateCompletionTrend(Goal $goal, int $days): string
    {
        $midPoint = new \DateTime("-" . ceil($days/2) . " days");
        $now = new \DateTime();

        $firstHalf = $this->progressRepository->findByGoalAndPeriod(
            $goal,
            new \DateTime("-{$days} days"),
            $midPoint
        );

        $secondHalf = $this->progressRepository->findByGoalAndPeriod(
            $goal,
            $midPoint,
            $now
        );

        $firstHalfCount = count($firstHalf);
        $secondHalfCount = count($secondHalf);

        if ($secondHalfCount > $firstHalfCount * 1.2) {
            return 'accelerating';
        } elseif ($secondHalfCount < $firstHalfCount * 0.8) {
            return 'slowing';
        } else {
            return 'steady';
        }
    }

    private function generateProgressRecommendations(Goal $goal, float $consistencyRate): array
    {
        $recommendations = [];

        if ($consistencyRate < 50) {
            $recommendations[] = [
                'type' => 'consistency',
                'message' => 'Essayez de suivre votre progression plus régulièrement',
                'priority' => 'high'
            ];
        }

        $daysSinceLastProgress = $this->getDaysSinceLastProgress($goal->getProgressEntries()->toArray());
        if ($daysSinceLastProgress > 3) {
            $recommendations[] = [
                'type' => 'activity',
                'message' => 'Il est temps de reprendre votre objectif !',
                'priority' => 'high'
            ];
        }

        $completion = $goal->getCompletionPercentage();
        if ($completion > 80) {
            $recommendations[] = [
                'type' => 'completion',
                'message' => 'Vous êtes proche du but ! Dernier effort !',
                'priority' => 'medium'
            ];
        }

        return $recommendations;
    }
}
