<?php

namespace App\Controller;

use JsonException;
use App\Entity\Song;
use OpenApi\Attributes as OA;
use App\Repository\PoolRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SongController extends AbstractController
{


    #[Route('api/v1/song', name: 'api_get_all_song', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Retourne la collection de son',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Song::class, groups: ['song']))
        )
    )]
    #[OA\Parameter(
        name: ''
    )]
    /**
     * Retour de tous les sons
     * @param \App\Repository\SongRepository $songRepository
     * @param \Symfony\Contracts\Cache\TagAwareCacheInterface $cache
     * @param \Symfony\Component\Serializer\SerializerInterface $serializer
     * @return JsonResponse
     */
    public function getAllV2(
        SongRepository $songRepository,
        TagAwareCacheInterface $cache,
        SerializerInterface $serializer
    ): JsonResponse {


        $idCache = "getAllSongs";
        $jsonData = $cache->get($idCache, function (ItemInterface $item) use ($songRepository, $serializer) {
            $data = $songRepository->findAll();
            return $serializer->serialize($data, 'json', ['groups' => ["song"]]);
        });

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);

    }

    #[Route('api/v1/song/{id}', name: 'api_get_song', methods: ['GET'])]
    #[OA\Tag('Songs')]
    #[OA\Response(
        response: 200,
        description: 'Retourne le son de l\'id correspondant',
        content: new OA\JsonContent(
            items: new OA\Items(ref: new Model(type: Song::class, groups: ['song']))
        )
    )]
    public function get(Song $id, SongRepository $songRepository, SerializerInterface $serializer): JsonResponse
    {
        $jsonData = $serializer->serialize($id, 'json');
        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('api/v1/song', name: 'api_create_song', methods: ['POST'])]

    public function create(
        ValidatorInterface $validator,
        Request $request,
        PoolRepository $poolRepository,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): JsonResponse {

        $song = $serializer->deserialize($request->getContent(), Song::class, 'json');
        $idPool = $request->toArray()['idPool'] ?? null;
        $pool = $poolRepository->find($idPool);
        $song->addPool($pool);
        $song->setName($song->getName() ?? "Non Defini");
        $song->setStatus('on');
        $errors = $validator->validate($song);
        if ($errors->count() > 0) {
            $jsonErrors = $serializer->serialize($errors, 'json');
            return new JsonResponse($jsonErrors, JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $entityManager->persist($song);
        $entityManager->flush();
        $cache->invalidateTags(['songsCache']);
        $jsonData = $serializer->serialize($song, 'json', ['groups' => ['song']]);
        $location = $urlGenerator->generate('get_song', ["id" => $song->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonData, Response::HTTP_CREATED, ["Location" => $location], true);
    }


    #[Route('api/v1/song/{id}', name: 'api_update_song', methods: ['PATCH'])]
    public function update(
        Song $id,
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): JsonResponse {


        $song = $serializer->deserialize($request->getContent(), Song::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $id]);
        $entityManager->persist($song);
        $entityManager->flush();
        $cache->invalidateTags(['songsCache']);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/v1/song/{id}', name: 'api_delete_song', methods: ['DELETE'])]
    public function delete(Song $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {


        if ('' !== $request->getContent() && true === $request->toArray()['hard']) {
            $entityManager->remove($id);

        } else {
            $id->setStatus('off');
            $entityManager->persist($id);
        }
        $entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }


}
