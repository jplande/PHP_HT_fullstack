<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/categories')]
#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    #[Route('', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste des catégories',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Category::class, groups: ['category']))
        )
    )]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findActiveOrderedByDisplay();
        $jsonData = $this->serializer->serialize($categories, 'json', ['groups' => ['category']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_categories_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'une catégorie',
        content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['category']))
    )]
    public function get(Category $category): JsonResponse
    {
        $jsonData = $this->serializer->serialize($category, 'json', ['groups' => ['category']]);
        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Données pour créer une catégorie',
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'code'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Fitness'),
                new OA\Property(property: 'code', type: 'string', example: 'FITNESS'),
                new OA\Property(property: 'icon', type: 'string', example: '💪'),
                new OA\Property(property: 'color', type: 'string', example: '#FF6B6B'),
                new OA\Property(property: 'description', type: 'string', example: 'Exercices physiques'),
                new OA\Property(property: 'displayOrder', type: 'integer', example: 10)
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        $category = new Category();
        $category->setName($data['name'] ?? '');
        $category->setCode($data['code'] ?? '');
        $category->setIcon($data['icon'] ?? null);
        $category->setColor($data['color'] ?? null);
        $category->setDescription($data['description'] ?? null);
        $category->setDisplayOrder($data['displayOrder'] ?? 0);
        $category->setIsActive($data['isActive'] ?? true);

        // Validation
        $errors = $this->validator->validate($category);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $jsonData = $this->serializer->serialize($category, 'json', ['groups' => ['category']]);
            $location = $this->urlGenerator->generate('api_categories_get', ['id' => $category->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, ['Location' => $location], true);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_categories_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Category $category, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $category->setName($data['name']);
        }
        if (isset($data['code'])) {
            $category->setCode($data['code']);
        }
        if (isset($data['icon'])) {
            $category->setIcon($data['icon']);
        }
        if (isset($data['color'])) {
            $category->setColor($data['color']);
        }
        if (isset($data['description'])) {
            $category->setDescription($data['description']);
        }
        if (isset($data['displayOrder'])) {
            $category->setDisplayOrder($data['displayOrder']);
        }
        if (isset($data['isActive'])) {
            $category->setIsActive($data['isActive']);
        }

        // Validation
        $errors = $this->validator->validate($category);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_categories_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Category $category): JsonResponse
    {
        // Vérifier si la catégorie a des objectifs associés
        if (!$category->getGoals()->isEmpty()) {
            return $this->json(['error' => 'Impossible de supprimer une catégorie avec des objectifs associés'], Response::HTTP_CONFLICT);
        }

        try {
            $this->entityManager->remove($category);
            $this->entityManager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/popular', name: 'api_categories_popular', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de catégories populaires', in: 'query', required: false)]
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 5);
        $categories = $this->categoryRepository->findPopularCategories($limit);

        $jsonData = $this->serializer->serialize($categories, 'json', ['groups' => ['category']]);
        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/search', name: 'api_categories_search', methods: ['GET'])]
    #[OA\Parameter(name: 'q', description: 'Terme de recherche', in: 'query', required: true)]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q');

        if (strlen($query) < 2) {
            return $this->json(['error' => 'Le terme de recherche doit faire au moins 2 caractères'], Response::HTTP_BAD_REQUEST);
        }

        $categories = $this->categoryRepository->searchByName($query);
        $jsonData = $this->serializer->serialize($categories, 'json', ['groups' => ['category']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/statistics', name: 'api_categories_statistics', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        $statistics = $this->categoryRepository->getStatistics();
        return $this->json($statistics);
    }

    #[Route('/used', name: 'api_categories_used', methods: ['GET'])]
    public function used(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $categories = $this->categoryRepository->findUsedByUser($user);
        $jsonData = $this->serializer->serialize($categories, 'json', ['groups' => ['category']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/default', name: 'api_categories_create_default', methods: ['POST'])]
    public function createDefault(): JsonResponse
    {
        $defaultCategories = Category::getDefaultCategories();
        $created = 0;

        foreach ($defaultCategories as $categoryData) {
            $existing = $this->categoryRepository->findOneByCode($categoryData['code']);

            if (!$existing) {
                $category = new Category();
                $category->setName($categoryData['name']);
                $category->setCode($categoryData['code']);
                $category->setIcon($categoryData['icon']);
                $category->setColor($categoryData['color']);
                $category->setDescription($categoryData['description'] ?? null);

                $this->entityManager->persist($category);
                $created++;
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $this->json(['message' => "{$created} catégories par défaut créées"]);
    }
}
