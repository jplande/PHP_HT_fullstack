<?php

namespace App\Service;

use App\Entity\Goal;
use App\Entity\User;
use App\Entity\Metric;
use App\Repository\ProgressRepository;
use App\Repository\GoalRepository;
use App\Repository\SessionRepository;

class AnalyticsService
{
    public function __construct(
        private ProgressRepository $progressRepository,
        private GoalRepository $goalRepository,
        private SessionRepository $sessionRepository
    ) {}

    /**
     * Génère les données pour graphiques Chart.js
     */
    public function generateChartData(Goal $goal, string $chartType = 'line', int $days = 30): array
    {
        $chartData = $this->progressRepository->getChartDataForGoal($goal, $days);

        // Organiser par métrique
        $dataByMetric = [];
        foreach ($chartData as $entry) {
            $metricName = $entry['metric_name'];
            if (!isset($dataByMetric[$metricName])) {
                $dataByMetric[$metricName] = [
                    'label' => $metricName,
                    'unit' => $entry['metric_unit'],
                    'color' => $entry['metric_color'] ?? $this->getDefaultColor($metricName),
                    'data' => [],
                    'dates' => []
                ];
            }

            $dataByMetric[$metricName]['data'][] = $entry['value'];
            $dataByMetric[$metricName]['dates'][] = $entry['date']->format('Y-m-d');
        }

        return [
            'type' => $chartType,
            'data' => [
                'datasets' => array_values($dataByMetric)
            ],
            'options' => $this->getChartOptions($chartType)
        ];
    }

    /**
     * Calcule la tendance de progression d'un objectif
     */
    public function calculateProgressTrend(Goal $goal, int $days = 30): array
    {
        $progressions = $this->progressRepository->findByGoalAndPeriod(
            $goal,
            new \DateTime("-{$days} days"),
            new \DateTime()
        );

        if (count($progressions) < 2) {
            return [
                'trend' => 'insufficient_data',
                'direction' => 'neutral',
                'percentage_change' => 0,
                'confidence' => 'low'
            ];
        }

        // Grouper par métrique
        $metricData = [];
        foreach ($progressions as $progress) {
            $metricId = $progress->getMetric()->getId();
            if (!isset($metricData[$metricId])) {
                $metricData[$metricId] = [];
            }
            $metricData[$metricId][] = $progress->getValue();
        }

        // Analyser la tendance principale (métrique primaire)
        $primaryMetric = $goal->getPrimaryMetric();
        if (!$primaryMetric || !isset($metricData[$primaryMetric->getId()])) {
            return ['trend' => 'no_primary_metric'];
        }

        $values = $metricData[$primaryMetric->getId()];
        $trend = $this->calculateLinearTrend($values);

        return [
            'trend' => $trend['direction'],
            'direction' => $trend['direction'],
            'percentage_change' => $trend['percentage_change'],
            'confidence' => $trend['confidence'],
            'slope' => $trend['slope'],
            'r_squared' => $trend['r_squared']
        ];
    }

    /**
     * Analyse des séries (streaks) pour un utilisateur
     */
    public function getStreakAnalysis(User $user): array
    {
        $streakData = $this->progressRepository->getUserStreak($user);

        // Analyse plus détaillée
        $progressions = $this->progressRepository->getTodayProgress($user);
        $todayHasProgress = !empty($progressions);

        // Prédiction de continuation de série
        $prediction = $this->predictStreakContinuation($user, $streakData['current']);

        return [
            'current_streak' => $streakData['current'],
            'longest_streak' => $streakData['longest'],
            'today_completed' => $todayHasProgress,
            'prediction' => $prediction,
            'streak_type' => $this->getStreakType($streakData['current']),
            'motivation_message' => $this->getMotivationMessage($streakData, $todayHasProgress)
        ];
    }

    /**
     * Calcule le taux de completion d'un objectif
     */
    public function getCompletionRate(Goal $goal): array
    {
        $metrics = $goal->getMetrics();
        $completionData = [];

        foreach ($metrics as $metric) {
            $latest = $this->progressRepository->getLatestByGoal($goal);
            $metricProgress = array_filter($latest, fn($p) => $p->getMetric() === $metric);

            if (!empty($metricProgress)) {
                $progress = reset($metricProgress);
                $percentage = $progress->getProgressPercentage();

                $completionData[] = [
                    'metric' => $metric->getName(),
                    'current_value' => $progress->getValue(),
                    'target_value' => $metric->getTargetValue(),
                    'percentage' => $percentage,
                    'is_completed' => $percentage >= 100,
                    'days_remaining' => $this->estimateDaysToCompletion($goal, $metric)
                ];
            }
        }

        $overallCompletion = empty($completionData) ? 0 :
            array_sum(array_column($completionData, 'percentage')) / count($completionData);

        return [
            'overall_completion' => $overallCompletion,
            'metrics_completion' => $completionData,
            'is_goal_completed' => $overallCompletion >= 100,
            'estimated_completion_date' => $this->estimateCompletionDate($goal)
        ];
    }

    /**
     * Prédiction de fin d'objectif
     */
    public function predictGoalCompletion(Goal $goal): array
    {
        $trend = $this->calculateProgressTrend($goal);
        $completion = $this->getCompletionRate($goal);

        if ($trend['trend'] === 'insufficient_data') {
            return [
                'prediction' => 'insufficient_data',
                'estimated_date' => null,
                'confidence' => 'low'
            ];
        }

        $primaryMetric = $goal->getPrimaryMetric();
        if (!$primaryMetric) {
            return ['prediction' => 'no_primary_metric'];
        }

        $currentCompletion = $completion['overall_completion'];
        $remainingProgress = 100 - $currentCompletion;

        // Calcul basé sur la tendance actuelle
        if ($trend['direction'] === 'increasing' && $trend['slope'] > 0) {
            $daysToComplete = $remainingProgress / ($trend['slope'] * 7); // slope par semaine
            $estimatedDate = new \DateTime("+{$daysToComplete} days");

            return [
                'prediction' => 'completion_likely',
                'estimated_date' => $estimatedDate,
                'days_remaining' => ceil($daysToComplete),
                'confidence' => $trend['confidence'],
                'success_probability' => $this->calculateSuccessProbability($trend, $completion)
            ];
        } elseif ($trend['direction'] === 'decreasing') {
            return [
                'prediction' => 'needs_improvement',
                'estimated_date' => null,
                'confidence' => $trend['confidence'],
                'success_probability' => max(0, 50 - ($trend['percentage_change'] * 2))
            ];
        }

        return [
            'prediction' => 'stable_progress',
            'estimated_date' => null,
            'confidence' => 'medium'
        ];
    }

    /**
     * Dashboard analytics complet pour un utilisateur
     */
    public function getDashboardAnalytics(User $user, int $days = 30): array
    {
        $goals = $this->goalRepository->findActiveByUser($user);
        $progressStats = $this->progressRepository->getProgressStats($user, $days);
        $sessionStats = $this->sessionRepository->getStatsForUser($user, $days);
        $streakAnalysis = $this->getStreakAnalysis($user);

        // Analyse des objectifs
        $goalAnalytics = [];
        foreach ($goals as $goal) {
            $completion = $this->getCompletionRate($goal);
            $trend = $this->calculateProgressTrend($goal, $days);

            $goalAnalytics[] = [
                'goal' => $goal,
                'completion' => $completion['overall_completion'],
                'trend' => $trend['direction'],
                'prediction' => $this->predictGoalCompletion($goal)
            ];
        }

        return [
            'user_stats' => [
                'total_goals' => count($goals),
                'progress_entries' => $progressStats['total_entries'],
                'active_days' => $progressStats['active_days'],
                'avg_satisfaction' => $progressStats['avg_satisfaction'],
                'sessions_completed' => $sessionStats['completed_sessions'],
                'total_time' => $sessionStats['total_duration']
            ],
            'streak_analysis' => $streakAnalysis,
            'goals_analytics' => $goalAnalytics,
            'recommendations' => $this->generateRecommendations($user, $goalAnalytics),
            'performance_score' => $this->calculatePerformanceScore($user, $progressStats, $sessionStats)
        ];
    }

    /**
     * Comparaison de performance entre périodes
     */
    public function comparePerformancePeriods(User $user, \DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): array
    {
        $goals = $this->goalRepository->findActiveByUser($user);
        $comparisons = [];

        foreach ($goals as $goal) {
            $comparison = $this->progressRepository->comparePerformance(
                $goal, $period1Start, $period1End, $period2Start, $period2End
            );
            $comparisons[] = [
                'goal' => $goal,
                'comparison' => $comparison
            ];
        }

        // Statistiques globales
        $period1Sessions = $this->sessionRepository->findCompletedInPeriod($user, $period1Start, $period1End);
        $period2Sessions = $this->sessionRepository->findCompletedInPeriod($user, $period2Start, $period2End);

        return [
            'goals_comparison' => $comparisons,
            'sessions_comparison' => [
                'period1_count' => count($period1Sessions),
                'period2_count' => count($period2Sessions),
                'improvement' => count($period2Sessions) - count($period1Sessions)
            ],
            'overall_trend' => $this->calculateOverallTrend($comparisons)
        ];
    }

    /**
     * Méthodes privées utilitaires
     */
    private function calculateLinearTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) return ['direction' => 'neutral', 'confidence' => 'low'];

        $xSum = array_sum(range(0, $n - 1));
        $ySum = array_sum($values);
        $xySum = 0;
        $x2Sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $xySum += $i * $values[$i];
            $x2Sum += $i * $i;
        }

        $slope = ($n * $xySum - $xSum * $ySum) / ($n * $x2Sum - $xSum * $xSum);
        $intercept = ($ySum - $slope * $xSum) / $n;

        // Calcul R²
        $yMean = $ySum / $n;
        $ssRes = 0;
        $ssTot = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $i + $intercept;
            $ssRes += pow($values[$i] - $predicted, 2);
            $ssTot += pow($values[$i] - $yMean, 2);
        }

        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        $direction = abs($slope) < 0.1 ? 'stable' : ($slope > 0 ? 'increasing' : 'decreasing');
        $confidence = $rSquared > 0.7 ? 'high' : ($rSquared > 0.4 ? 'medium' : 'low');

        $firstValue = $values[0];
        $lastValue = end($values);
        $percentageChange = $firstValue != 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;

        return [
            'direction' => $direction,
            'slope' => $slope,
            'r_squared' => $rSquared,
            'confidence' => $confidence,
            'percentage_change' => $percentageChange
        ];
    }

    private function getDefaultColor(string $metricName): string
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
        ];
        return $colors[crc32($metricName) % count($colors)];
    }

    private function getChartOptions(string $chartType): array
    {
        $baseOptions = [
            'responsive' => true,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false]
            ],
            'scales' => [
                'x' => ['display' => true, 'title' => ['display' => true, 'text' => 'Date']],
                'y' => ['display' => true, 'title' => ['display' => true, 'text' => 'Valeur']]
            ]
        ];

        if ($chartType === 'line') {
            $baseOptions['elements'] = ['line' => ['tension' => 0.4]];
        }

        return $baseOptions;
    }

    private function predictStreakContinuation(User $user, int $currentStreak): array
    {
        $todayProgress = $this->progressRepository->getTodayProgress($user);
        $weekProgress = $this->progressRepository->getWeekProgress($user);

        $probability = 50; // Base

        // Facteurs d'augmentation
        if (!empty($todayProgress)) $probability += 30;
        if ($currentStreak > 7) $probability += 20;
        if (count($weekProgress) >= 5) $probability += 15;

        // Facteurs de diminution
        if ($currentStreak > 30) $probability -= 10; // Fatigue possible
        if (empty($weekProgress)) $probability -= 25;

        return [
            'probability' => max(0, min(100, $probability)),
            'factors' => [
                'today_completed' => !empty($todayProgress),
                'week_consistency' => count($weekProgress),
                'current_streak' => $currentStreak
            ]
        ];
    }

    private function getStreakType(int $streak): string
    {
        return match (true) {
            $streak >= 100 => 'legendary',
            $streak >= 50 => 'excellent',
            $streak >= 30 => 'great',
            $streak >= 14 => 'good',
            $streak >= 7 => 'promising',
            $streak >= 3 => 'building',
            default => 'starting'
        };
    }

    private function getMotivationMessage(array $streakData, bool $todayCompleted): string
    {
        $current = $streakData['current'];

        if ($todayCompleted) {
            return match (true) {
                $current >= 30 => "Incroyable ! {$current} jours consécutifs ! 🔥",
                $current >= 7 => "Excellent ! Une semaine complète ! 💪",
                $current >= 3 => "Bien joué ! Continuez sur cette lancée ! ⭐",
                default => "Parfait ! Votre série commence ! 🎯"
            };
        }

        return $current > 0
            ? "Ne cassez pas votre série de {$current} jours ! 🚀"
            : "C'est le moment de commencer une nouvelle série ! 💪";
    }

    private function estimateDaysToCompletion(Goal $goal, Metric $metric): ?int
    {
        $trend = $this->calculateProgressTrend($goal);

        if ($trend['direction'] !== 'increasing' || $trend['slope'] <= 0) {
            return null;
        }

        $current = $metric->getCurrentValue() ?? $metric->getInitialValue();
        $target = $metric->getTargetValue();
        $remaining = abs($target - $current);

        return ceil($remaining / ($trend['slope'] * 7)); // slope par semaine
    }

    private function estimateCompletionDate(Goal $goal): ?\DateTime
    {
        $primaryMetric = $goal->getPrimaryMetric();
        if (!$primaryMetric) return null;

        $days = $this->estimateDaysToCompletion($goal, $primaryMetric);
        return $days ? new \DateTime("+{$days} days") : null;
    }

    private function calculateSuccessProbability(array $trend, array $completion): float
    {
        $base = 50.0;

        // Facteurs positifs
        if ($trend['direction'] === 'increasing') $base += 20;
        if ($trend['confidence'] === 'high') $base += 15;
        if ($completion['overall_completion'] > 75) $base += 20;

        // Facteurs négatifs
        if ($trend['direction'] === 'decreasing') $base -= 30;
        if ($trend['confidence'] === 'low') $base -= 15;

        return max(0, min(100, $base));
    }

    private function generateRecommendations(User $user, array $goalAnalytics): array
    {
        $recommendations = [];

        foreach ($goalAnalytics as $analytics) {
            if ($analytics['trend'] === 'decreasing') {
                $recommendations[] = [
                    'type' => 'improvement_needed',
                    'goal' => $analytics['goal']->getTitle(),
                    'message' => 'Cet objectif nécessite plus d\'attention'
                ];
            }

            if ($analytics['completion'] > 90) {
                $recommendations[] = [
                    'type' => 'near_completion',
                    'goal' => $analytics['goal']->getTitle(),
                    'message' => 'Vous êtes proche du but ! Dernier effort !'
                ];
            }
        }

        return $recommendations;
    }

    private function calculatePerformanceScore(User $user, array $progressStats, array $sessionStats): int
    {
        $score = 0;

        // Progression régulière (40 points max)
        $score += min(40, $progressStats['active_days'] * 2);

        // Satisfaction (30 points max)
        $score += ($progressStats['avg_satisfaction'] ?? 0) * 6;

        // Sessions complétées (30 points max)
        $score += min(30, $sessionStats['completed_sessions'] * 3);

        return min(100, $score);
    }

    private function calculateOverallTrend(array $comparisons): string
    {
        $improvements = 0;
        $total = count($comparisons);

        foreach ($comparisons as $comparison) {
            if ($comparison['comparison']['improvement']['avg_value'] > 0) {
                $improvements++;
            }
        }

        $ratio = $total > 0 ? $improvements / $total : 0;

        return match (true) {
            $ratio >= 0.7 => 'improving',
            $ratio >= 0.3 => 'stable',
            default => 'declining'
        };
    }
}
