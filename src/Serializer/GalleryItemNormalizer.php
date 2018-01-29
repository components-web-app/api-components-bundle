<?php

namespace Silverback\ApiComponentBundle\Serializer;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class GalleryItemNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private $decorated;
    private $projectDir;
    private $imagineCacheManager;

    public function __construct(
        NormalizerInterface $decorated,
        string $projectDir,
        CacheManager $imagineCacheManager
    )
    {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', DenormalizerInterface::class));
        }

        $this->decorated = $decorated;
        $this->projectDir = $projectDir;
        $this->imagineCacheManager = $imagineCacheManager;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    /**
     * @param object $object
     * @param null $format
     * @param array $context
     * @return array|\Symfony\Component\Serializer\Normalizer\scalar
     * @throws \Symfony\Component\Serializer\Exception\LogicException
     * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $data = $this->decorated->normalize($object, $format, $context);
        if ($object instanceof GalleryItem) {
            $fs = new Filesystem();
            $filePath = $this->projectDir . '/public/' . $data['filePath'];
            if (\is_array($data) && $fs->exists($filePath)) {
                list($width, $height) = getimagesize($filePath);
                $data['thumbnailPath'] = parse_url(
                    $this->imagineCacheManager->getBrowserPath($data['filePath'], 'thumbnail'),
                    PHP_URL_PATH
                );
                $data['placeholderPath'] = parse_url(
                    $this->imagineCacheManager->getBrowserPath($data['filePath'], 'placeholder'),
                    PHP_URL_PATH
                );
            } else {
                $width = $height = 0;
            }
            $data['width'] = $width;
            $data['height'] = $height;
        }
        return $data;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $class, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        if($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
