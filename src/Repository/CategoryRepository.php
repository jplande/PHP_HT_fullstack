<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Trouve toutes les catégories actives, triées par ordre d'affichage
     */
    public function findActiveOrderedByDisplay(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.displayOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une catégorie par son code
     */
    public function findOneByCode(string $code): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche de catégories par nom
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query')
            ->andWhere('c.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégories avec le nombre d'objectifs associés
     */
    public function findWithGoalCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(g.id) as goal_count')
            ->leftJoin('c.goals', 'g')
            ->andWhere('c.isActive = :active')
            ->groupBy('c.id')
            ->setParameter('active', true)
            ->orderBy('c.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégories populaires (avec le plus d'objectifs actifs)
     */
    public function findPopularCategories(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(g.id) as active_goals_count')
            ->leftJoin('c.goals', 'g', 'WITH', 'g.status = :active')
            ->andWhere('c.isActive = :categoryActive')
            ->groupBy('c.id')
            ->having('active_goals_count > 0')
            ->orderBy('active_goals_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('active', 'active')
            ->setParameter('categoryActive', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par catégorie
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                c.name,
                c.code,
                c.color,
                c.icon,
                COUNT(g.id) as total_goals,
                SUM(CASE WHEN g.status = :active THEN 1 ELSE 0 END) as active_goals,
                SUM(CASE WHEN g.status = :completed THEN 1 ELSE 0 END) as completed_goals
            ')
            ->leftJoin('c.goals', 'g')
            ->andWhere('c.isActive = :categoryActive')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.color', 'c.icon')
            ->setParameter('active', 'active')
            ->setParameter('completed', 'completed')
            ->setParameter('categoryActive', true)
            ->orderBy('c.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories utilisées par un utilisateur spécifique
     */
    public function findUsedByUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.goals', 'g')
            ->andWhere('g.user = :user')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une catégorie
     */
    public function save(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une catégorie
     */
    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
