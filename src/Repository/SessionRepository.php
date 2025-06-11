<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\Goal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Trouve toutes les sessions d'un objectif
     */
    public function findByGoal(Goal $goal): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.goal = :goal')
            ->setParameter('goal', $goal)
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les sessions d'un utilisateur
     */
    public function findByUser(User $user, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.startTime', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Sessions en cours pour un utilisateur
     */
    public function findInProgressByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->andWhere('s.completed = :completed')
            ->andWhere('s.endTime IS NULL')
            ->setParameter('user', $user)
            ->setParameter('completed', false)
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions complétées par période
     */
    public function findCompletedInPeriod(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->leftJoin('g.category', 'c')
            ->addSelect('g', 'c')
            ->andWhere('g.user = :user')
            ->andWhere('s.completed = :completed')
            ->andWhere('s.startTime BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions d'aujourd'hui pour un utilisateur
     */
    public function findTodayByUser(User $user): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->leftJoin('g.category', 'c')
            ->addSelect('g', 'c')
            ->andWhere('g.user = :user')
            ->andWhere('s.startTime >= :today')
            ->andWhere('s.startTime < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions de la semaine pour un utilisateur
     */
    public function findWeekByUser(User $user, ?\DateTime $weekStart = null): array
    {
        if (!$weekStart) {
            $weekStart = new \DateTime('monday this week');
            $weekStart->setTime(0, 0, 0);
        }

        $weekEnd = clone $weekStart;
        $weekEnd->add(new \DateInterval('P7D'));

        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->andWhere('s.startTime >= :weekStart')
            ->andWhere('s.startTime < :weekEnd')
            ->setParameter('user', $user)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des sessions pour un utilisateur
     */
    public function getStatsForUser(User $user, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('s')
            ->select('
                COUNT(s.id) as total_sessions,
                SUM(CASE WHEN s.completed = true THEN 1 ELSE 0 END) as completed_sessions,
                AVG(s.duration) as avg_duration,
                SUM(s.duration) as total_duration,
                AVG(s.satisfactionRating) as avg_satisfaction,
                AVG(s.intensityRating) as avg_intensity,
                AVG(s.difficultyRating) as avg_difficulty
            ')
            ->leftJoin('s.goal', 'g')
            ->andWhere('g.user = :user')
            ->andWhere('s.startTime >= :fromDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Sessions les plus longues
     */
    public function findLongestSessions(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->andWhere('s.completed = :completed')
            ->andWhere('s.duration IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->orderBy('s.duration', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions par lieu (location)
     */
    public function findByLocation(User $user, string $location): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->andWhere('s.location = :location')
            ->setParameter('user', $user)
            ->setParameter('location', $location)
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Lieux les plus utilisés
     */
    public function getPopularLocations(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.location, COUNT(s.id) as session_count')
            ->leftJoin('s.goal', 'g')
            ->andWhere('g.user = :user')
            ->andWhere('s.location IS NOT NULL')
            ->andWhere('s.location != :empty')
            ->groupBy('s.location')
            ->orderBy('session_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();
    }

    /**
     * Analyse des performances par heure de la journée
     */
    public function getHourlyPerformance(User $user, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('s')
            ->select('
                HOUR(s.startTime) as hour,
                COUNT(s.id) as session_count,
                AVG(s.duration) as avg_duration,
                AVG(s.satisfactionRating) as avg_satisfaction
            ')
            ->leftJoin('s.goal', 'g')
            ->andWhere('g.user = :user')
            ->andWhere('s.completed = :completed')
            ->andWhere('s.startTime >= :fromDate')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions avec notes
     */
    public function findWithNotes(Goal $goal, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.goal = :goal')
            ->andWhere('s.notes IS NOT NULL')
            ->andWhere('s.notes != :empty')
            ->setParameter('goal', $goal)
            ->setParameter('empty', '')
            ->orderBy('s.startTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de sessions
     */
    public function searchSessions(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.goal', 'g')
            ->addSelect('g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user);

        if (isset($filters['goal_id'])) {
            $qb->andWhere('s.goal = :goal')
                ->setParameter('goal', $filters['goal_id']);
        }

        if (isset($filters['completed'])) {
            $qb->andWhere('s.completed = :completed')
                ->setParameter('completed', $filters['completed']);
        }

        if (isset($filters['location'])) {
            $qb->andWhere('s.location LIKE :location')
                ->setParameter('location', '%' . $filters['location'] . '%');
        }

        if (isset($filters['start_date'])) {
            $qb->andWhere('s.startTime >= :startDate')
                ->setParameter('startDate', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $qb->andWhere('s.startTime <= :endDate')
                ->setParameter('endDate', $filters['end_date']);
        }

        if (isset($filters['min_duration'])) {
            $qb->andWhere('s.duration >= :minDuration')
                ->setParameter('minDuration', $filters['min_duration']);
        }

        return $qb->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une session
     */
    public function save(Session $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une session
     */
    public function remove(Session $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
