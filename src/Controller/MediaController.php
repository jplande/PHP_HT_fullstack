<?php

namespace App\Controller;

use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class MediaController extends AbstractController
{
    #[Route('/', name: 'app_media')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/MediaController.php',
        ]);
    }
    #[Route('/api/media/{media}', name: 'api_get_media', methods: ['GET'])]
    public function get(Media $media, UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $location = $urlGenerator->generate("app_media", [], UrlGeneratorInterface::ABSOLUTE_URL);
        $location = $location . str_replace("/public/", "", $media->getPublicPath() . "/" . $media->getRealPath());

        return $media ?
            new JsonResponse($serializer->serialize($media, "json", []), Response::HTTP_OK, ["Location" => $location], true) :
            new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/media', name: 'api_create_media', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $media = new Media();
        $file = $request->files->get('file');

        $media->setFile($file)
            ->setRealName($file->getClientOriginalName())
            ->setPublicPath('media')
            ->setStatus('on')
            ->setUploadedAt(new \DateTime());
        $entityManager->persist($media);
        $entityManager->flush();
        $jsonFile = $serializer->serialize($media, "json");
        $location = $urlGenerator->generate('api_get_media', ["media" => $media->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonFile, Response::HTTP_CREATED, ["Location" => $location], true);
    }

}
