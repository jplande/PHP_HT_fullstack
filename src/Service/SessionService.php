<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\Goal;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Repository\ProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\SessionStartedEvent;
use App\Event\SessionCompletedEvent;

class SessionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionRepository $sessionRepository,
        private ProgressRepository $progressRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ProgressService $progressService
    ) {}

    /**
     * Démarre une nouvelle session d'entraînement
     */
    public function startSession(Goal $goal, array $sessionData = []): Session
    {
        // Vérifier qu'il n'y a pas déjà une session en cours pour cet objectif
        $ongoingSessions = $this->sessionRepository->findInProgressByUser($goal->getUser());

        foreach ($ongoingSessions as $session) {
            if ($session->getGoal() === $goal) {
                throw new \InvalidArgumentException('Une session est déjà en cours pour cet objectif');
            }
        }

        $session = new Session();
        $session->setGoal($goal);
        $session->setStartTime($sessionData['startTime'] ?? new \DateTime());
        $session->setLocation($sessionData['location'] ?? null);
        $session->setNotes($sessionData['notes'] ?? null);

        // Ajouter des données de session si fournies
        if (isset($sessionData['sessionData'])) {
            $session->setSessionData($sessionData['sessionData']);
        }

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        // Dispatcher l'événement
        $event = new SessionStartedEvent($session);
        $this->eventDispatcher->dispatch($event, SessionStartedEvent::NAME);

        return $session;
    }

    /**
     * Termine une session d'entraînement
     */
    public function completeSession(Session $session, array $completionData = []): Session
    {
        if ($session->getCompleted()) {
            throw new \InvalidArgumentException('Cette session est déjà terminée');
        }

        $session->setEndTime($completionData['endTime'] ?? new \DateTime());
        $session->setCompleted(true);

        // Ajouter les évaluations
        if (isset($completionData['intensityRating'])) {
            $session->setIntensityRating($completionData['intensityRating']);
        }

        if (isset($completionData['satisfactionRating'])) {
            $session->setSatisfactionRating($completionData['satisfactionRating']);
        }

        if (isset($completionData['difficultyRating'])) {
            $session->setDifficultyRating($completionData['difficultyRating']);
        }

        if (isset($completionData['notes'])) {
            $session->setNotes($completionData['notes']);
        }

        // Enregistrer automatiquement les progressions si fournies
        if (isset($completionData['progressions'])) {
            $this->recordSessionProgress($session, $completionData['progressions']);
        }

        $this->entityManager->flush();

        // Dispatcher l'événement
        $event = new SessionCompletedEvent($session);
        $this->eventDispatcher->dispatch($event, SessionCompletedEvent::NAME);

        return $session;
    }

    /**
     * Met à jour une session en cours
     */
    public function updateSession(Session $session, array $updateData): Session
    {
        if (isset($updateData['notes'])) {
            $session->setNotes($updateData['notes']);
        }

        if (isset($updateData['location'])) {
            $session->setLocation($updateData['location']);
        }

        if (isset($updateData['sessionData'])) {
            $existingData = $session->getSessionData() ?? [];
            $session->setSessionData(array_merge($existingData, $updateData['sessionData']));
        }

        $this->entityManager->flush();

        return $session;
    }

    /**
     * Abandonne une session (sans la marquer comme complétée)
     */
    public function abandonSession(Session $session, string $reason = null): Session
    {
        $session->setEndTime(new \DateTime());
        $session->setCompleted(false);

        if ($reason) {
            $session->addSessionData('abandon_reason', $reason);
        }

        $this->entityManager->flush();

        return $session;
    }

    /**
     * Enregistre des progressions liées à une session
     */
    public function recordSessionProgress(Session $session, array $progressionsData): array
    {
        $recordedProgress = [];

        foreach ($progressionsData as $progressData) {
            $progressData['date'] = $session->getStartTime();

            $progress = $this->progressService->recordProgress(
                $session->getGoal(),
                $progressData['metric'],
                $progressData
            );

            $progress->setSession($session);
            $this->entityManager->persist($progress);

            $recordedProgress[] = $progress;
        }

        $this->entityManager->flush();

        return $recordedProgress;
    }

    /**
     * Calcule les statistiques d'une session
     */
    public function getSessionStatistics(Session $session): array
    {
        $progressEntries = $session->getProgressEntries();
        $goal = $session->getGoal();

        $stats = [
            'duration_minutes' => $session->getDurationInMinutes(),
            'progress_entries' => count($progressEntries),
            'metrics_updated' => count(array_unique(array_map(fn($p) => $p->getMetric()->getId(), $progressEntries->toArray()))),
            'average_ratings' => $session->getAverageRating(),
            'completion_impact' => $this->calculateCompletionImpact($session)
        ];

        // Ajouter des statistiques de performance si disponibles
        if (!empty($progressEntries)) {
            $values = array_map(fn($p) => $p->getValue(), $progressEntries->toArray());
            $stats['performance'] = [
                'total_value' => array_sum($values),
                'average_value' => array_sum($values) / count($values),
                'max_value' => max($values),
                'improvement_vs_previous' => $this->calculateImprovementVsPrevious($session)
            ];
        }

        return $stats;
    }

    /**
     * Analyse les patterns de sessions pour un utilisateur
     */
    public function analyzeUserSessionPatterns(User $user, int $days = 30): array
    {
        $sessions = $this->sessionRepository->findCompletedInPeriod(
            $user,
            new \DateTime("-{$days} days"),
            new \DateTime()
        );

        if (empty($sessions)) {
            return [
                'total_sessions' => 0,
                'message' => 'Aucune session complétée dans la période'
            ];
        }

        // Analyser les patterns temporels
        $hourlyDistribution = [];
        $dailyDistribution = [];
        $durationAnalysis = [];

        foreach ($sessions as $session) {
            // Distribution horaire
            $hour = $session->getStartTime()->format('H');
            $hourlyDistribution[$hour] = ($hourlyDistribution[$hour] ?? 0) + 1;

            // Distribution quotidienne
            $day = $session->getStartTime()->format('N'); // 1 = Lundi, 7 = Dimanche
            $dailyDistribution[$day] = ($dailyDistribution[$day] ?? 0) + 1;

            // Analyse de durée
            if ($session->getDuration()) {
                $durationAnalysis[] = $session->getDurationInMinutes();
            }
        }

        // Analyser les performances
        $performanceAnalysis = $this->analyzeSessionPerformance($sessions);

        return [
            'total_sessions' => count($sessions),
            'average_duration' => !empty($durationAnalysis) ? array_sum($durationAnalysis) / count($durationAnalysis) : 0,
            'preferred_hours' => $this->findPreferredTimes($hourlyDistribution),
            'preferred_days' => $this->findPreferredDays($dailyDistribution),
            'performance_trends' => $performanceAnalysis,
            'consistency_score' => $this->calculateSessionConsistency($user, $days),
            'recommendations' => $this->generateSessionRecommendations($sessions, $hourlyDistribution, $dailyDistribution)
        ];
    }

    /**
     * Compare les performances entre sessions
     */
    public function compareSessionPerformance(Session $session1, Session $session2): array
    {
        $stats1 = $this->getSessionStatistics($session1);
        $stats2 = $this->getSessionStatistics($session2);

        return [
            'session1' => $stats1,
            'session2' => $stats2,
            'improvements' => [
                'duration' => $stats2['duration_minutes'] - $stats1['duration_minutes'],
                'progress_entries' => $stats2['progress_entries'] - $stats1['progress_entries'],
                'average_rating' => ($stats2['average_ratings'] ?? 0) - ($stats1['average_ratings'] ?? 0)
            ],
            'better_session' => $this->determineBetterSession($stats1, $stats2)
        ];
    }

    /**
     * Génère un programme de sessions basé sur l'historique
     */
    public function generateSessionPlan(User $user, Goal $goal, int $daysAhead = 7): array
    {
        $patterns = $this->analyzeUserSessionPatterns($user);
        $goalHistory = $this->sessionRepository->findByGoal($goal);

        $plan = [];
        $preferredHours = $patterns['preferred_hours'] ?? [];
        $preferredDays = $patterns['preferred_days'] ?? [];

        for ($i = 1; $i <= $daysAhead; $i++) {
            $date = new \DateTime("+{$i} days");
            $dayOfWeek = $date->format('N');

            // Recommander une session si c'est un jour préféré
            if (in_array($dayOfWeek, $preferredDays) || empty($preferredDays)) {
                $recommendedHour = !empty($preferredHours) ? $preferredHours[0] : '18';

                $plan[] = [
                    'date' => $date->format('Y-m-d'),
                    'recommended_time' => $recommendedHour . ':00',
                    'estimated_duration' => $patterns['average_duration'] ?? 30,
                    'priority' => $this->calculateSessionPriority($goal, $date),
                    'suggested_focus' => $this->suggestSessionFocus($goal, $goalHistory)
                ];
            }
        }

        return $plan;
    }

    /**
     * Exporte les données de sessions en CSV
     */
    public function exportSessionsToCSV(User $user, \DateTime $startDate, \DateTime $endDate): string
    {
        $sessions = $this->sessionRepository->findCompletedInPeriod($user, $startDate, $endDate);

        $csvData = [];
        $csvData[] = [
            'Date', 'Objectif', 'Durée (min)', 'Lieu', 'Intensité', 'Satisfaction',
            'Difficulté', 'Progressions', 'Notes'
        ];

        foreach ($sessions as $session) {
            $csvData[] = [
                $session->getStartTime()->format('Y-m-d H:i'),
                $session->getGoal()->getTitle(),
                $session->getDurationInMinutes(),
                $session->getLocation() ?? '',
                $session->getIntensityRating() ?? '',
                $session->getSatisfactionRating() ?? '',
                $session->getDifficultyRating() ?? '',
                count($session->getProgressEntries()),
                $session->getNotes() ?? ''
            ];
        }

        return $this->arrayToCsv($csvData);
    }

    /**
     * Calcule les objectifs les plus travaillés
     */
    public function getMostActiveGoals(User $user, int $days = 30, int $limit = 5): array
    {
        $sessions = $this->sessionRepository->findCompletedInPeriod(
            $user,
            new \DateTime("-{$days} days"),
            new \DateTime()
        );

        $goalStats = [];

        foreach ($sessions as $session) {
            $goalId = $session->getGoal()->getId();

            if (!isset($goalStats[$goalId])) {
                $goalStats[$goalId] = [
                    'goal' => $session->getGoal(),
                    'session_count' => 0,
                    'total_duration' => 0,
                    'average_satisfaction' => 0,
                    'total_progressions' => 0
                ];
            }

            $goalStats[$goalId]['session_count']++;
            $goalStats[$goalId]['total_duration'] += $session->getDurationInMinutes();
            $goalStats[$goalId]['total_progressions'] += count($session->getProgressEntries());

            if ($session->getSatisfactionRating()) {
                $goalStats[$goalId]['average_satisfaction'] += $session->getSatisfactionRating();
            }
        }

        // Calculer les moyennes et trier
        foreach ($goalStats as &$stats) {
            $stats['average_duration'] = $stats['total_duration'] / $stats['session_count'];
            $stats['average_satisfaction'] = $stats['average_satisfaction'] / $stats['session_count'];
        }

        uasort($goalStats, fn($a, $b) => $b['session_count'] <=> $a['session_count']);

        return array_slice(array_values($goalStats), 0, $limit);
    }

    /**
     * Méthodes privées utilitaires
     */
    private function calculateCompletionImpact(Session $session): float
    {
        $goal = $session->getGoal();
        $progressEntries = $session->getProgressEntries();

        if ($progressEntries->isEmpty()) {
            return 0.0;
        }

        $totalImpact = 0.0;
        foreach ($progressEntries as $progress) {
            $metric = $progress->getMetric();
            $progressPercentage = $progress->getProgressPercentage();

            // Impact pondéré par l'importance de la métrique
            $weight = $metric->getIsPrimary() ? 1.0 : 0.5;
            $totalImpact += $progressPercentage * $weight;
        }

        return $totalImpact / count($progressEntries);
    }

    private function calculateImprovementVsPrevious(Session $session): float
    {
        $goal = $session->getGoal();
        $previousSessions = $this->sessionRepository->findByGoal($goal);

        // Trouver la session précédente
        $previousSession = null;
        foreach ($previousSessions as $prevSession) {
            if ($prevSession->getStartTime() < $session->getStartTime() &&
                $prevSession->getCompleted() &&
                $prevSession->getId() !== $session->getId()) {
                if (!$previousSession || $prevSession->getStartTime() > $previousSession->getStartTime()) {
                    $previousSession = $prevSession;
                }
            }
        }

        if (!$previousSession) {
            return 0.0;
        }

        // Comparer les performances moyennes
        $currentStats = $this->getSessionStatistics($session);
        $previousStats = $this->getSessionStatistics($previousSession);

        if (isset($currentStats['performance']['average_value']) && isset($previousStats['performance']['average_value'])) {
            $currentAvg = $currentStats['performance']['average_value'];
            $previousAvg = $previousStats['performance']['average_value'];

            return $previousAvg != 0 ? (($currentAvg - $previousAvg) / $previousAvg) * 100 : 0;
        }

        return 0.0;
    }

    private function analyzeSessionPerformance(array $sessions): array
    {
        $satisfactionRatings = [];
        $intensityRatings = [];
        $difficultyRatings = [];
        $durations = [];

        foreach ($sessions as $session) {
            if ($session->getSatisfactionRating()) {
                $satisfactionRatings[] = $session->getSatisfactionRating();
            }
            if ($session->getIntensityRating()) {
                $intensityRatings[] = $session->getIntensityRating();
            }
            if ($session->getDifficultyRating()) {
                $difficultyRatings[] = $session->getDifficultyRating();
            }
            if ($session->getDuration()) {
                $durations[] = $session->getDurationInMinutes();
            }
        }

        return [
            'average_satisfaction' => !empty($satisfactionRatings) ? array_sum($satisfactionRatings) / count($satisfactionRatings) : 0,
            'average_intensity' => !empty($intensityRatings) ? array_sum($intensityRatings) / count($intensityRatings) : 0,
            'average_difficulty' => !empty($difficultyRatings) ? array_sum($difficultyRatings) / count($difficultyRatings) : 0,
            'duration_trend' => $this->calculateDurationTrend($durations),
            'performance_consistency' => $this->calculatePerformanceConsistency($satisfactionRatings)
        ];
    }

    private function findPreferredTimes(array $hourlyDistribution): array
    {
        arsort($hourlyDistribution);
        return array_slice(array_keys($hourlyDistribution), 0, 3);
    }

    private function findPreferredDays(array $dailyDistribution): array
    {
        arsort($dailyDistribution);
        return array_slice(array_keys($dailyDistribution), 0, 3);
    }

    private function calculateSessionConsistency(User $user, int $days): float
    {
        $sessions = $this->sessionRepository->findCompletedInPeriod(
            $user,
            new \DateTime("-{$days} days"),
            new \DateTime()
        );

        $sessionDates = array_unique(array_map(fn($s) => $s->getStartTime()->format('Y-m-d'), $sessions));

        return (count($sessionDates) / $days) * 100;
    }

    private function generateSessionRecommendations(array $sessions, array $hourlyDistribution, array $dailyDistribution): array
    {
        $recommendations = [];

        // Recommandations basées sur la fréquence
        if (count($sessions) < 10) {
            $recommendations[] = [
                'type' => 'frequency',
                'message' => 'Essayez d\'augmenter la fréquence de vos sessions pour de meilleurs résultats.',
                'priority' => 'medium'
            ];
        }

        // Recommandations basées sur les horaires
        $preferredHours = $this->findPreferredTimes($hourlyDistribution);
        if (!empty($preferredHours)) {
            $recommendations[] = [
                'type' => 'timing',
                'message' => "Vos meilleures performances sont vers {$preferredHours[0]}h. Planifiez vos sessions importantes à cette heure.",
                'priority' => 'low'
            ];
        }

        // Recommandations basées sur la durée
        $durations = array_map(fn($s) => $s->getDurationInMinutes(), array_filter($sessions, fn($s) => $s->getDuration()));
        if (!empty($durations)) {
            $avgDuration = array_sum($durations) / count($durations);
            if ($avgDuration < 20) {
                $recommendations[] = [
                    'type' => 'duration',
                    'message' => 'Vos sessions sont courtes. Essayez d\'augmenter progressivement leur durée.',
                    'priority' => 'medium'
                ];
            }
        }

        return $recommendations;
    }

    private function determineBetterSession(array $stats1, array $stats2): string
    {
        $score1 = 0;
        $score2 = 0;

        // Comparer différents critères
        if (($stats1['average_ratings'] ?? 0) > ($stats2['average_ratings'] ?? 0)) $score1++;
        else $score2++;

        if ($stats1['progress_entries'] > $stats2['progress_entries']) $score1++;
        else $score2++;

        if ($stats1['duration_minutes'] > $stats2['duration_minutes']) $score1++;
        else $score2++;

        return $score1 > $score2 ? 'session1' : ($score2 > $score1 ? 'session2' : 'equal');
    }

    private function calculateSessionPriority(Goal $goal, \DateTime $date): string
    {
        $completion = $goal->getCompletionPercentage();
        $daysSinceLastSession = $this->getDaysSinceLastSession($goal);

        if ($completion < 30 && $daysSinceLastSession > 7) {
            return 'high';
        } elseif ($completion < 70 && $daysSinceLastSession > 3) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function suggestSessionFocus(Goal $goal, array $goalHistory): string
    {
        $metrics = $goal->getMetrics();
        $leastProgressedMetric = null;
        $minProgress = 100;

        foreach ($metrics as $metric) {
            $progress = $metric->getProgressPercentage();
            if ($progress < $minProgress) {
                $minProgress = $progress;
                $leastProgressedMetric = $metric;
            }
        }

        return $leastProgressedMetric ?
            "Focus sur: " . $leastProgressedMetric->getName() :
            "Session d'entraînement général";
    }

    private function getDaysSinceLastSession(Goal $goal): int
    {
        $lastSession = $this->sessionRepository->findOneBy(
            ['goal' => $goal, 'completed' => true],
            ['startTime' => 'DESC']
        );

        if (!$lastSession) {
            return 999;
        }

        return (new \DateTime())->diff($lastSession->getStartTime())->days;
    }

    private function calculateDurationTrend(array $durations): string
    {
        if (count($durations) < 3) {
            return 'insufficient_data';
        }

        $firstThird = array_slice($durations, 0, ceil(count($durations) / 3));
        $lastThird = array_slice($durations, -ceil(count($durations) / 3));

        $avgFirst = array_sum($firstThird) / count($firstThird);
        $avgLast = array_sum($lastThird) / count($lastThird);

        if ($avgLast > $avgFirst * 1.1) {
            return 'increasing';
        } elseif ($avgLast < $avgFirst * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    private function calculatePerformanceConsistency(array $ratings): float
    {
        if (empty($ratings)) {
            return 0.0;
        }

        $mean = array_sum($ratings) / count($ratings);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $ratings)) / count($ratings);
        $standardDeviation = sqrt($variance);

        // Consistance inversement proportionnelle à l'écart-type
        return max(0, 100 - ($standardDeviation * 20));
    }

    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
