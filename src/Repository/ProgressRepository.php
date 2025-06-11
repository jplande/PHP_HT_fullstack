<?php

namespace App\Repository;

use App\Entity\Progress;
use App\Entity\Goal;
use App\Entity\Metric;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Progress>
 */
class ProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Progress::class);
    }

    /**
     * Trouve les progressions d'un objectif pour une période donnée
     */
    public function findByGoalAndPeriod(Goal $goal, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.metric', 'm')
            ->addSelect('m')
            ->andWhere('p.goal = :goal')
            ->andWhere('p.date BETWEEN :startDate AND :endDate')
            ->setParameter('goal', $goal)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.date', 'ASC')
            ->addOrderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les progressions d'une métrique spécifique
     */
    public function findByMetric(Metric $metric, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.metric = :metric')
            ->setParameter('metric', $metric)
            ->orderBy('p.date', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Données pour graphiques - progressions groupées par métrique
     */
    public function getChartDataForGoal(Goal $goal, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->select('
                m.name as metric_name,
                m.unit as metric_unit,
                m.color as metric_color,
                p.date,
                p.value,
                p.notes
            ')
            ->leftJoin('p.metric', 'm')
            ->andWhere('p.goal = :goal')
            ->andWhere('p.date >= :fromDate')
            ->setParameter('goal', $goal)
            ->setParameter('fromDate', $fromDate)
            ->orderBy('p.date', 'ASC')
            ->addOrderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernière progression pour chaque métrique d'un objectif
     */
    public function getLatestByGoal(Goal $goal): array
    {
        $subQuery = $this->createQueryBuilder('p_sub')
            ->select('MAX(p_sub.date)')
            ->andWhere('p_sub.goal = :goal')
            ->andWhere('p_sub.metric = p.metric')
            ->getDQL();

        return $this->createQueryBuilder('p')
            ->leftJoin('p.metric', 'm')
            ->addSelect('m')
            ->andWhere('p.goal = :goal')
            ->andWhere('p.date = (' . $subQuery . ')')
            ->setParameter('goal', $goal)
            ->orderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Progressions d'aujourd'hui pour un utilisateur
     */
    public function getTodayProgress(User $user): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('p')
            ->leftJoin('p.goal', 'g')
            ->leftJoin('p.metric', 'm')
            ->leftJoin('g.category', 'c')
            ->addSelect('g', 'm', 'c')
            ->andWhere('g.user = :user')
            ->andWhere('p.date >= :today')
            ->andWhere('p.date < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Progressions de la semaine pour un utilisateur
     */
    public function getWeekProgress(User $user, ?\DateTime $weekStart = null): array
    {
        if (!$weekStart) {
            $weekStart = new \DateTime('monday this week');
            $weekStart->setTime(0, 0, 0);
        }

        $weekEnd = clone $weekStart;
        $weekEnd->add(new \DateInterval('P7D'));

        return $this->createQueryBuilder('p')
            ->leftJoin('p.goal', 'g')
            ->leftJoin('p.metric', 'm')
            ->addSelect('g', 'm')
            ->andWhere('g.user = :user')
            ->andWhere('p.date >= :weekStart')
            ->andWhere('p.date < :weekEnd')
            ->setParameter('user', $user)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les tendances pour une métrique
     */
    public function getTrendAnalysis(Metric $metric, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        $result = $this->createQueryBuilder('p')
            ->select('
                COUNT(p.id) as total_entries,
                AVG(p.value) as average_value,
                MIN(p.value) as min_value,
                MAX(p.value) as max_value,
                MIN(p.date) as first_date,
                MAX(p.date) as last_date
            ')
            ->andWhere('p.metric = :metric')
            ->andWhere('p.date >= :fromDate')
            ->setParameter('metric', $metric)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleResult();

        // Calcul de la tendance (régression linéaire simple)
        $progressions = $this->findByMetric($metric);
        $trend = $this->calculateTrend($progressions);
        $result['trend'] = $trend;

        return $result;
    }

    /**
     * Série de jours consécutifs pour un utilisateur
     */
    public function getUserStreak(User $user): array
    {
        // Trouve toutes les dates uniques de progression
        $dates = $this->createQueryBuilder('p')
            ->select('DISTINCT DATE(p.date) as progress_date')
            ->leftJoin('p.goal', 'g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->orderBy('progress_date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->calculateStreakFromDates(array_column($dates, 'progress_date'));
    }

    /**
     * Progression moyenne par jour de la semaine
     */
    public function getWeeklyPattern(Goal $goal, int $weeks = 8): array
    {
        $fromDate = new \DateTime("-{$weeks} weeks");

        return $this->createQueryBuilder('p')
            ->select('
                DAYOFWEEK(p.date) as day_of_week,
                COUNT(p.id) as total_entries,
                AVG(p.value) as average_value
            ')
            ->andWhere('p.goal = :goal')
            ->andWhere('p.date >= :fromDate')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week', 'ASC')
            ->setParameter('goal', $goal)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Progressions avec notes pour un objectif
     */
    public function findWithNotes(Goal $goal, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.metric', 'm')
            ->addSelect('m')
            ->andWhere('p.goal = :goal')
            ->andWhere('p.notes IS NOT NULL')
            ->andWhere('p.notes != :empty')
            ->setParameter('goal', $goal)
            ->setParameter('empty', '')
            ->orderBy('p.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de progressions par valeur ou date
     */
    public function searchProgress(Goal $goal, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.metric', 'm')
            ->addSelect('m')
            ->andWhere('p.goal = :goal')
            ->setParameter('goal', $goal);

        if (isset($filters['metric_id'])) {
            $qb->andWhere('p.metric = :metric')
                ->setParameter('metric', $filters['metric_id']);
        }

        if (isset($filters['min_value'])) {
            $qb->andWhere('p.value >= :minValue')
                ->setParameter('minValue', $filters['min_value']);
        }

        if (isset($filters['max_value'])) {
            $qb->andWhere('p.value <= :maxValue')
                ->setParameter('maxValue', $filters['max_value']);
        }

        if (isset($filters['start_date'])) {
            $qb->andWhere('p.date >= :startDate')
                ->setParameter('startDate', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $qb->andWhere('p.date <= :endDate')
                ->setParameter('endDate', $filters['end_date']);
        }

        return $qb->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques de progression pour dashboard
     */
    public function getProgressStats(User $user, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->select('
                COUNT(p.id) as total_entries,
                COUNT(DISTINCT p.goal) as active_goals,
                COUNT(DISTINCT DATE(p.date)) as active_days,
                AVG(p.satisfactionRating) as avg_satisfaction,
                AVG(p.difficultyRating) as avg_difficulty
            ')
            ->leftJoin('p.goal', 'g')
            ->andWhere('g.user = :user')
            ->andWhere('p.date >= :fromDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Comparaison des progressions entre deux périodes
     */
    public function comparePerformance(Goal $goal, \DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): array
    {
        $period1 = $this->createQueryBuilder('p1')
            ->select('
                COUNT(p1.id) as entries,
                AVG(p1.value) as avg_value,
                MAX(p1.value) as max_value,
                AVG(p1.satisfactionRating) as avg_satisfaction
            ')
            ->andWhere('p1.goal = :goal')
            ->andWhere('p1.date BETWEEN :p1Start AND :p1End')
            ->setParameter('goal', $goal)
            ->setParameter('p1Start', $period1Start)
            ->setParameter('p1End', $period1End)
            ->getQuery()
            ->getSingleResult();

        $period2 = $this->createQueryBuilder('p2')
            ->select('
                COUNT(p2.id) as entries,
                AVG(p2.value) as avg_value,
                MAX(p2.value) as max_value,
                AVG(p2.satisfactionRating) as avg_satisfaction
            ')
            ->andWhere('p2.goal = :goal')
            ->andWhere('p2.date BETWEEN :p2Start AND :p2End')
            ->setParameter('goal', $goal)
            ->setParameter('p2Start', $period2Start)
            ->setParameter('p2End', $period2End)
            ->getQuery()
            ->getSingleResult();

        return [
            'period1' => $period1,
            'period2' => $period2,
            'improvement' => [
                'entries' => $period2['entries'] - $period1['entries'],
                'avg_value' => $period2['avg_value'] - $period1['avg_value'],
                'max_value' => $period2['max_value'] - $period1['max_value'],
            ]
        ];
    }

    /**
     * Calcule la tendance linéaire simple
     */
    private function calculateTrend(array $progressions): string
    {
        if (count($progressions) < 2) {
            return 'insufficient_data';
        }

        $values = array_map(fn($p) => $p->getValue(), $progressions);
        $firstHalf = array_slice($values, 0, floor(count($values) / 2));
        $secondHalf = array_slice($values, ceil(count($values) / 2));

        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);

        if ($avgSecond > $avgFirst * 1.05) {
            return 'increasing';
        } elseif ($avgSecond < $avgFirst * 0.95) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Calcule la série à partir des dates
     */
    private function calculateStreakFromDates(array $dates): array
    {
        if (empty($dates)) {
            return ['current' => 0, 'longest' => 0];
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $tempStreak = 1;

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Vérifier si aujourd'hui fait partie de la série
        $lastDate = new \DateTime($dates[0]);
        $daysDiff = $today->diff($lastDate)->days;

        if ($daysDiff <= 1) {
            $currentStreak = 1;
        }

        // Calculer les séries
        for ($i = 1; $i < count($dates); $i++) {
            $currentDate = new \DateTime($dates[$i]);
            $previousDate = new \DateTime($dates[$i - 1]);
            $diff = $previousDate->diff($currentDate)->days;

            if ($diff === 1) {
                $tempStreak++;
                if ($i === 1 && $currentStreak > 0) {
                    $currentStreak = $tempStreak;
                }
            } else {
                $longestStreak = max($longestStreak, $tempStreak);
                $tempStreak = 1;
                if ($i === 1) {
                    $currentStreak = 0;
                }
            }
        }

        $longestStreak = max($longestStreak, $tempStreak);

        return [
            'current' => $currentStreak,
            'longest' => $longestStreak
        ];
    }

    /**
     * Sauvegarde une progression
     */
    public function save(Progress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une progression
     */
    public function remove(Progress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
