<?php

namespace App\Repository;

use App\Entity\Metric;
use App\Entity\Goal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Metric>
 */
class MetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Metric::class);
    }

    /**
     * Trouve toutes les métriques d'un objectif, triées par ordre d'affichage
     */
    public function findByGoalOrderedByDisplay(Goal $goal): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.goal = :goal')
            ->setParameter('goal', $goal)
            ->orderBy('m.displayOrder', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la métrique principale d'un objectif
     */
    public function findPrimaryByGoal(Goal $goal): ?Metric
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.goal = :goal')
            ->andWhere('m.isPrimary = :primary')
            ->setParameter('goal', $goal)
            ->setParameter('primary', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les métriques par type d'évolution
     */
    public function findByEvolutionType(string $evolutionType): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.evolutionType = :type')
            ->setParameter('type', $evolutionType)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Métriques avec leurs dernières progressions
     */
    public function findWithLatestProgress(Goal $goal): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.progressEntries', 'p')
            ->addSelect('p')
            ->andWhere('m.goal = :goal')
            ->setParameter('goal', $goal)
            ->orderBy('m.displayOrder', 'ASC')
            ->addOrderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des métriques pour un objectif
     */
    public function getMetricsStats(Goal $goal): array
    {
        return $this->createQueryBuilder('m')
            ->select('
                m.id,
                m.name,
                m.unit,
                m.evolutionType,
                m.initialValue,
                m.targetValue,
                COUNT(p.id) as progress_count,
                MAX(p.value) as max_value,
                MIN(p.value) as min_value,
                AVG(p.value) as avg_value,
                MAX(p.date) as last_progress_date
            ')
            ->leftJoin('m.progressEntries', 'p')
            ->andWhere('m.goal = :goal')
            ->groupBy('m.id', 'm.name', 'm.unit', 'm.evolutionType', 'm.initialValue', 'm.targetValue')
            ->setParameter('goal', $goal)
            ->orderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Métriques les plus utilisées (avec le plus de progressions)
     */
    public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m, COUNT(p.id) as progress_count')
            ->leftJoin('m.progressEntries', 'p')
            ->groupBy('m.id')
            ->orderBy('progress_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de métriques par nom ou unité
     */
    public function searchByNameOrUnit(string $query): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.name LIKE :query OR m.unit LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Métriques atteignant leur objectif
     */
    public function findTargetReached(Goal $goal): array
    {
        // Cette méthode nécessite une logique métier plus complexe
        // On récupère les métriques et on vérifie en PHP
        $metrics = $this->findByGoalOrderedByDisplay($goal);

        return array_filter($metrics, function(Metric $metric) {
            return $metric->isTargetReached();
        });
    }

    /**
     * Unités les plus utilisées
     */
    public function getPopularUnits(int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.unit, COUNT(m.id) as usage_count')
            ->groupBy('m.unit')
            ->orderBy('usage_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Métriques d'un utilisateur via ses objectifs
     */
    public function findByUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.goal', 'g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.updatedAt', 'DESC')
            ->addOrderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Métriques similaires (même unité et type d'évolution)
     */
    public function findSimilar(Metric $metric, int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id != :metricId')
            ->andWhere('m.unit = :unit')
            ->andWhere('m.evolutionType = :evolutionType')
            ->setParameter('metricId', $metric->getId())
            ->setParameter('unit', $metric->getUnit())
            ->setParameter('evolutionType', $metric->getEvolutionType())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une métrique
     */
    public function save(Metric $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une métrique
     */
    public function remove(Metric $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
