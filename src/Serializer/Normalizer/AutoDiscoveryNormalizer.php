<?php

namespace App\Serializer\Normalizer;

use App\Entity\Song;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AutoDiscoveryNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private UrlGeneratorInterface $urlGenerator

    ) {
    }

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        $className = (new ReflectionClass($object))->getShortName();
        $className = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        $data['_links'] = [
            "up" => [
                "method" => ['GET'],
                "path" => $this->urlGenerator->generate("api_get_all_" . $className)
            ],
            "self" => [
                "method" => ['GET'],
                "path" => $this->urlGenerator->generate("api_get_" . $className, ["id" => $data["id"]])
            ]
        ];
        // TODO: add, edit, or delete some data

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        // return true;
        return ($data instanceof Song) && $format === "json";
        // TODO: return $data instanceof Object
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Song::class => true];
    }
}
