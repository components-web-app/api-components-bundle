<?php

namespace Silverback\ApiComponentBundle\Serializer;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private $decorated;
    private $projectDir;
    private $imagineCacheManager;

    /**
     * FileNormalizer constructor.
     * @param NormalizerInterface $decorated
     * @param string $projectDir
     * @param CacheManager $imagineCacheManager
     * @throws \InvalidArgumentException
     */
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

    /**
     * @param mixed $data
     * @param null $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    /**
     * @param $object
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
        if ($object instanceof FileInterface) {
            /* @var $object FileInterface */
            $filePath = $this->getRealFilePath($object->getFilePath());
            if ($filePath) {
                if (false !== \exif_imagetype($filePath)) {
                    [$width, $height] = getimagesize($filePath);
                } else {
                    $width = $height = 0;
                }
                $data['width'] = $width;
                $data['height'] = $height;

                $supported = $this->isImagineSupportedFile($object->getFilePath());
                foreach ($object::getImagineFilters() as $returnKey => $filter)
                {
                    $data[$returnKey] = $supported ? parse_url(
                        $this->imagineCacheManager->getBrowserPath($object->getFilePath(), $filter),
                        PHP_URL_PATH
                    ) : null;
                }
            }
        }
        return $data;
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param null $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object
     * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @throws \Symfony\Component\Serializer\Exception\RuntimeException
     * @throws \Symfony\Component\Serializer\Exception\LogicException
     * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Serializer\Exception\ExtraAttributesException
     * @throws \Symfony\Component\Serializer\Exception\BadMethodCallException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $class, $format, $context);
    }

    /**
     * @param SerializerInterface $serializer
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    /**
     * @param null|string $filePath
     * @return null|string
     */
    private function getRealFilePath(?string $filePath): ?string
    {
        if (!$filePath || trim($filePath) === '') {
            return null;
        }
        $fs = new Filesystem();
        $filePath = $this->projectDir . '/public/' . $filePath;
        return $fs->exists($filePath) ? $filePath : null;
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function isImagineSupportedFile(?string $filePath): bool
    {
        $filePath = $this->getRealFilePath($filePath);
        if (!$filePath) {
            return false;
        }
        $imageType = \exif_imagetype($filePath);
        if (false === $imageType) {
            return false;
        }
        return \in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }
}
