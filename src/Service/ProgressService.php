<?php

namespace App\Service;

use App\Entity\Progress;
use App\Entity\Goal;
use App\Entity\Metric;
use App\Entity\User;
use App\Repository\ProgressRepository;
use App\Repository\GoalRepository;
use App\Repository\MetricRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\ProgressRecordedEvent;

class ProgressService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProgressRepository $progressRepository,
        private GoalRepository $goalRepository,
        private MetricRepository $metricRepository,
        private EventDispatcherInterface $eventDispatcher,
        private AchievementService $achievementService,
        private GoalService $goalService
    ) {}

    /**
     * Enregistre une nouvelle progression
     */
    public function recordProgress(Goal $goal, Metric $metric, array $progressData): Progress
    {
        // Vérifier si une progression existe déjà pour cette date
        $existingProgress = $this->findExistingProgress($goal, $metric, $progressData['date']);

        if ($existingProgress) {
            return $this->updateProgress($existingProgress, $progressData);
        }

        $progress = new Progress();
        $progress->setGoal($goal);
        $progress->setMetric($metric);
        $progress->setValue($progressData['value']);
        $progress->setDate($progressData['date'] ?? new \DateTime());
        $progress->setNotes($progressData['notes'] ?? null);
        $progress->setDifficultyRating($progressData['difficultyRating'] ?? null);
        $progress->setSatisfactionRating($progressData['satisfactionRating'] ?? null);

        // Ajouter des métadonnées si fournies
        if (isset($progressData['metadata'])) {
            $progress->setMetadata($progressData['metadata']);
        }

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        // Mettre à jour la série de l'utilisateur
        $this->updateUserStreak($goal->getUser());

        // Vérifier la completion automatique de l'objectif
        $this->goalService->checkGoalAutoCompletion($goal);

        // Dispatcher l'événement
        $event = new ProgressRecordedEvent($progress);
        $this->eventDispatcher->dispatch($event, ProgressRecordedEvent::NAME);

        // Vérifier les badges
        $this->achievementService->checkAndUnlockAchievements($goal->getUser());

        return $progress;
    }

    /**
     * Met à jour une progression existante
     */
    public function updateProgress(Progress $progress, array $progressData): Progress
    {
        if (isset($progressData['value'])) {
            $progress->setValue($progressData['value']);
        }

        if (isset($progressData['notes'])) {
            $progress->setNotes($progressData['notes']);
        }

        if (isset($progressData['difficultyRating'])) {
            $progress->setDifficultyRating($progressData['difficultyRating']);
        }

        if (isset($progressData['satisfactionRating'])) {
            $progress->setSatisfactionRating($progressData['satisfactionRating']);
        }

        if (isset($progressData['metadata'])) {
            $existingMetadata = $progress->getMetadata() ?? [];
            $progress->setMetadata(array_merge($existingMetadata, $progressData['metadata']));
        }

        $this->entityManager->flush();

        return $progress;
    }

    /**
     * Enregistre des progressions multiples en une fois
     */
    public function recordMultipleProgress(Goal $goal, array $progressesData): array
    {
        $recordedProgress = [];

        foreach ($progressesData as $progressData) {
            $metric = $this->metricRepository->find($progressData['metricId']);
            if ($metric && $metric->getGoal() === $goal) {
                $progress = $this->recordProgress($goal, $metric, $progressData);
                $recordedProgress[] = $progress;
            }
        }

        return $recordedProgress;
    }

    /**
     * Supprime une progression
     */
    public function deleteProgress(Progress $progress): void
    {
        $user = $progress->getGoal()->getUser();

        $this->entityManager->remove($progress);
        $this->entityManager->flush();

        // Recalculer la série utilisateur
        $this->updateUserStreak($user);
    }

    /**
     * Importe des progressions depuis un fichier CSV
     */
    public function importProgressFromCsv(Goal $goal, string $csvContent): array
    {
        $lines = str_getcsv($csvContent, "\n");
        $headers = str_getcsv(array_shift($lines));

        $importedProgress = [];
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            try {
                $data = array_combine($headers, str_getcsv($line));
                $progress = $this->createProgressFromCsvData($goal, $data);

                if ($progress) {
                    $importedProgress[] = $progress;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'line' => $lineNumber + 2,
                    'error' => $e->getMessage(),
                    'data' => $line
                ];
            }
        }

        return [
            'imported' => $importedProgress,
            'errors' => $errors,
            'total_processed' => count($lines),
            'success_count' => count($importedProgress)
        ];
    }

    /**
     * Génère des données pour graphiques
     */
    public function generateChartData(Goal $goal, string $chartType = 'line', array $options = []): array
    {
        $days = $options['days'] ?? 30;
        $metricsFilter = $options['metrics'] ?? null;

        $chartData = $this->progressRepository->getChartDataForGoal($goal, $days);

        // Filtrer par métriques si spécifié
        if ($metricsFilter) {
            $chartData = array_filter($chartData, fn($entry) =>
            in_array($entry['metric_name'], $metricsFilter)
            );
        }

        // Organiser les données par métrique
        $datasets = [];
        $labels = [];

        foreach ($chartData as $entry) {
            $metricName = $entry['metric_name'];
            $date = $entry['date']->format('Y-m-d');

            if (!isset($datasets[$metricName])) {
                $datasets[$metricName] = [
                    'label' => $metricName . ' (' . $entry['metric_unit'] . ')',
                    'data' => [],
                    'backgroundColor' => $entry['metric_color'] ?? $this->generateMetricColor($metricName),
                    'borderColor' => $entry['metric_color'] ?? $this->generateMetricColor($metricName),
                    'fill' => $chartType === 'area'
                ];
            }

            $datasets[$metricName]['data'][] = [
                'x' => $date,
                'y' => $entry['value']
            ];

            if (!in_array($date, $labels)) {
                $labels[] = $date;
            }
        }

        sort($labels);

        return [
            'type' => $chartType,
            'data' => [
                'labels' => $labels,
                'datasets' => array_values($datasets)
            ],
            'options' => $this->getChartOptions($chartType, $options)
        ];
    }

    /**
     * Calcule les statistiques de progression pour un utilisateur
     */
    public function getUserProgressStatistics(User $user, int $days = 30): array
    {
        $progressStats = $this->progressRepository->getProgressStats($user, $days);
        $streakData = $this->progressRepository->getUserStreak($user);
        $todayProgress = $this->progressRepository->getTodayProgress($user);
        $weekProgress = $this->progressRepository->getWeekProgress($user);

        return [
            'period_stats' => $progressStats,
            'streak_info' => $streakData,
            'today' => [
                'entries_count' => count($todayProgress),
                'goals_updated' => count(array_unique(array_map(fn($p) => $p->getGoal()->getId(), $todayProgress))),
                'average_satisfaction' => $this->calculateAverageSatisfaction($todayProgress)
            ],
            'week' => [
                'entries_count' => count($weekProgress),
                'active_days' => count(array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $weekProgress))),
                'consistency_rate' => $this->calculateWeeklyConsistency($user)
            ],
            'performance_trends' => $this->calculatePerformanceTrends($user, $days)
        ];
    }

    /**
     * Prédit les prochaines valeurs basées sur les tendances
     */
    public function predictNextValues(Metric $metric, int $daysAhead = 7): array
    {
        $historicalData = $this->progressRepository->findByMetric($metric, 30);

        if (count($historicalData) < 3) {
            return [
                'predictions' => [],
                'confidence' => 'low',
                'message' => 'Pas assez de données pour faire une prédiction'
            ];
        }

        $values = array_map(fn($p) => $p->getValue(), $historicalData);
        $trend = $this->calculateLinearTrend($values);

        $predictions = [];
        $lastValue = end($values);

        for ($i = 1; $i <= $daysAhead; $i++) {
            $predictedValue = $lastValue + ($trend['slope'] * $i);
            $predictions[] = [
                'date' => (new \DateTime("+{$i} days"))->format('Y-m-d'),
                'predicted_value' => round($predictedValue, 2),
                'confidence_interval' => [
                    'min' => round($predictedValue * 0.9, 2),
                    'max' => round($predictedValue * 1.1, 2)
                ]
            ];
        }

        return [
            'predictions' => $predictions,
            'confidence' => $trend['confidence'],
            'trend_direction' => $trend['direction'],
            'base_trend' => $trend
        ];
    }

    /**
     * Analyse les patterns de progression
     */
    public function analyzeProgressPatterns(Goal $goal): array
    {
        $weeklyPattern = $this->progressRepository->getWeeklyPattern($goal);
        $progressData = $this->progressRepository->getChartDataForGoal($goal, 60);

        // Analyser les jours de la semaine les plus actifs
        $dayAnalysis = [];
        $dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

        foreach ($weeklyPattern as $pattern) {
            $dayIndex = $pattern['day_of_week'] - 1; // MySQL DAYOFWEEK starts at 1
            $dayAnalysis[$dayNames[$dayIndex]] = [
                'entries' => $pattern['total_entries'],
                'average_value' => round($pattern['average_value'], 2)
            ];
        }

        // Analyser les patterns temporels
        $timePatterns = $this->analyzeTimePatterns($progressData);

        return [
            'weekly_patterns' => $dayAnalysis,
            'best_day' => $this->findBestPerformanceDay($dayAnalysis),
            'time_patterns' => $timePatterns,
            'consistency_score' => $this->calculateConsistencyScore($goal),
            'recommendations' => $this->generatePatternRecommendations($dayAnalysis, $timePatterns)
        ];
    }

    /**
     * Compare les performances entre différentes périodes
     */
    public function comparePerformancePeriods(Goal $goal, \DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): array
    {
        $comparison = $this->progressRepository->comparePerformance(
            $goal, $period1Start, $period1End, $period2Start, $period2End
        );

        // Ajouter des analyses détaillées
        $period1Data = $this->progressRepository->findByGoalAndPeriod($goal, $period1Start, $period1End);
        $period2Data = $this->progressRepository->findByGoalAndPeriod($goal, $period2Start, $period2End);

        $analysis = [
            'raw_comparison' => $comparison,
            'detailed_analysis' => [
                'period1' => $this->analyzePeriodData($period1Data),
                'period2' => $this->analyzePeriodData($period2Data)
            ],
            'improvement_areas' => $this->identifyImprovementAreas($comparison),
            'success_factors' => $this->identifySuccessFactors($period1Data, $period2Data)
        ];

        return $analysis;
    }

    /**
     * Méthodes privées utilitaires
     */
    private function findExistingProgress(Goal $goal, Metric $metric, \DateTime $date): ?Progress
    {
        return $this->progressRepository->findOneBy([
            'goal' => $goal,
            'metric' => $metric,
            'date' => $date
        ]);
    }

    private function updateUserStreak(User $user): void
    {
        $user->updateStreak();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function createProgressFromCsvData(Goal $goal, array $data): ?Progress
    {
        // Validation et mapping des données CSV
        $requiredFields = ['metric_name', 'value', 'date'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Champ requis manquant: {$field}");
            }
        }

        // Trouver la métrique par nom
        $metric = null;
        foreach ($goal->getMetrics() as $m) {
            if ($m->getName() === $data['metric_name']) {
                $metric = $m;
                break;
            }
        }

        if (!$metric) {
            throw new \InvalidArgumentException("Métrique non trouvée: " . $data['metric_name']);
        }

        $progressData = [
            'value' => floatval($data['value']),
            'date' => new \DateTime($data['date']),
            'notes' => $data['notes'] ?? null,
            'difficultyRating' => isset($data['difficulty']) ? intval($data['difficulty']) : null,
            'satisfactionRating' => isset($data['satisfaction']) ? intval($data['satisfaction']) : null
        ];

        return $this->recordProgress($goal, $metric, $progressData);
    }

    private function generateMetricColor(string $metricName): string
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
        ];
        return $colors[crc32($metricName) % count($colors)];
    }

    private function getChartOptions(string $chartType, array $options): array
    {
        $baseOptions = [
            'responsive' => true,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false]
            ],
            'scales' => [
                'x' => ['type' => 'time', 'time' => ['unit' => 'day']],
                'y' => ['beginAtZero' => $options['beginAtZero'] ?? false]
            ]
        ];

        if ($chartType === 'line') {
            $baseOptions['elements'] = ['line' => ['tension' => 0.4]];
        }

        return $baseOptions;
    }

    private function calculateAverageSatisfaction(array $progressEntries): float
    {
        $satisfactionRatings = array_filter(
            array_map(fn($p) => $p->getSatisfactionRating(), $progressEntries)
        );

        return empty($satisfactionRatings) ? 0 :
            array_sum($satisfactionRatings) / count($satisfactionRatings);
    }

    private function calculateWeeklyConsistency(User $user): float
    {
        $weekProgress = $this->progressRepository->getWeekProgress($user);
        $uniqueDays = array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $weekProgress));

        return (count($uniqueDays) / 7) * 100;
    }

    private function calculatePerformanceTrends(User $user, int $days): array
    {
        $activeGoals = $this->goalRepository->findActiveByUser($user);
        $trends = [];

        foreach ($activeGoals as $goal) {
            $progressData = $this->progressRepository->getChartDataForGoal($goal, $days);

            if (!empty($progressData)) {
                $values = array_column($progressData, 'value');
                $trend = $this->calculateLinearTrend($values);

                $trends[] = [
                    'goal_title' => $goal->getTitle(),
                    'trend' => $trend['direction'],
                    'confidence' => $trend['confidence'],
                    'improvement' => $trend['percentage_change']
                ];
            }
        }

        return $trends;
    }

    private function calculateLinearTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['direction' => 'insufficient_data', 'confidence' => 'low', 'slope' => 0, 'percentage_change' => 0];
        }

        $xSum = array_sum(range(0, $n - 1));
        $ySum = array_sum($values);
        $xySum = 0;
        $x2Sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $xySum += $i * $values[$i];
            $x2Sum += $i * $i;
        }

        $slope = ($n * $xySum - $xSum * $ySum) / ($n * $x2Sum - $xSum * $xSum);

        $direction = abs($slope) < 0.1 ? 'stable' : ($slope > 0 ? 'increasing' : 'decreasing');

        $firstValue = $values[0];
        $lastValue = end($values);
        $percentageChange = $firstValue != 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;

        return [
            'direction' => $direction,
            'slope' => $slope,
            'confidence' => abs($slope) > 0.5 ? 'high' : 'medium',
            'percentage_change' => $percentageChange
        ];
    }

    private function analyzeTimePatterns(array $progressData): array
    {
        $hourlyPatterns = [];

        foreach ($progressData as $entry) {
            $hour = $entry['date']->format('H');
            if (!isset($hourlyPatterns[$hour])) {
                $hourlyPatterns[$hour] = ['count' => 0, 'total_value' => 0];
            }
            $hourlyPatterns[$hour]['count']++;
            $hourlyPatterns[$hour]['total_value'] += $entry['value'];
        }

        // Calculer les moyennes
        foreach ($hourlyPatterns as $hour => &$data) {
            $data['average_value'] = $data['total_value'] / $data['count'];
        }

        return [
            'hourly_distribution' => $hourlyPatterns,
            'peak_hour' => $this->findPeakHour($hourlyPatterns),
            'most_productive_time' => $this->findMostProductiveTime($hourlyPatterns)
        ];
    }

    private function findBestPerformanceDay(array $dayAnalysis): ?string
    {
        $bestDay = null;
        $maxEntries = 0;

        foreach ($dayAnalysis as $day => $data) {
            if ($data['entries'] > $maxEntries) {
                $maxEntries = $data['entries'];
                $bestDay = $day;
            }
        }

        return $bestDay;
    }

    private function calculateConsistencyScore(Goal $goal): float
    {
        $progressData = $this->progressRepository->getChartDataForGoal($goal, 30);
        $uniqueDays = array_unique(array_map(fn($entry) => $entry['date']->format('Y-m-d'), $progressData));

        $expectedDays = match ($goal->getFrequencyType()) {
            'daily' => 30,
            'weekly' => 4,
            'monthly' => 1,
            default => 30
        };

        return min(100, (count($uniqueDays) / $expectedDays) * 100);
    }

    private function generatePatternRecommendations(array $dayAnalysis, array $timePatterns): array
    {
        $recommendations = [];

        // Recommandations basées sur les jours
        $bestDay = $this->findBestPerformanceDay($dayAnalysis);
        if ($bestDay) {
            $recommendations[] = [
                'type' => 'best_day',
                'message' => "Votre meilleur jour est {$bestDay}. Planifiez vos objectifs les plus importants ce jour-là."
            ];
        }

        // Recommandations basées sur les heures
        $peakHour = $timePatterns['peak_hour'] ?? null;
        if ($peakHour) {
            $recommendations[] = [
                'type' => 'peak_time',
                'message' => "Vous êtes le plus actif vers {$peakHour}h. Utilisez ce créneau pour vos objectifs prioritaires."
            ];
        }

        return $recommendations;
    }

    private function findPeakHour(array $hourlyPatterns): ?string
    {
        $maxCount = 0;
        $peakHour = null;

        foreach ($hourlyPatterns as $hour => $data) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $peakHour = $hour;
            }
        }

        return $peakHour;
    }

    private function findMostProductiveTime(array $hourlyPatterns): ?string
    {
        $maxAverage = 0;
        $productiveHour = null;

        foreach ($hourlyPatterns as $hour => $data) {
            if ($data['average_value'] > $maxAverage) {
                $maxAverage = $data['average_value'];
                $productiveHour = $hour;
            }
        }

        return $productiveHour;
    }

    private function analyzePeriodData(array $progressData): array
    {
        if (empty($progressData)) {
            return ['entries' => 0, 'average_value' => 0, 'consistency' => 0];
        }

        $values = array_map(fn($p) => $p->getValue(), $progressData);
        $uniqueDays = array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $progressData));

        return [
            'entries' => count($progressData),
            'average_value' => array_sum($values) / count($values),
            'max_value' => max($values),
            'min_value' => min($values),
            'active_days' => count($uniqueDays),
            'consistency' => count($uniqueDays) / 30 * 100 // Assuming 30-day periods
        ];
    }

    private function identifyImprovementAreas(array $comparison): array
    {
        $improvements = [];

        if ($comparison['improvement']['entries'] < 0) {
            $improvements[] = 'Fréquence des enregistrements';
        }

        if ($comparison['improvement']['avg_value'] < 0) {
            $improvements[] = 'Performance moyenne';
        }

        if ($comparison['improvement']['max_value'] < 0) {
            $improvements[] = 'Performance maximale';
        }

        return $improvements;
    }

    private function identifySuccessFactors(array $period1Data, array $period2Data): array
    {
        $factors = [];

        $period1Analysis = $this->analyzePeriodData($period1Data);
        $period2Analysis = $this->analyzePeriodData($period2Data);

        if ($period2Analysis['consistency'] > $period1Analysis['consistency']) {
            $factors[] = 'Amélioration de la régularité';
        }

        if ($period2Analysis['entries'] > $period1Analysis['entries']) {
            $factors[] = 'Augmentation de la fréquence d\'enregistrement';
        }

        return $factors;
    }
}
