<?php

namespace Silverback\ApiComponentBundle\DataTransformer;

use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Factory\FileDataFactory;

final class FileDataTransformer extends AbstractDataTransformer
{
    /**
     * @param FileInterface $object
     * @return FileInterface
     */
    public function transform($object, array $context = []): FileInterface
    {
        /** @var FileDataFactory $factory */
        $factory = $this->container->get(FileDataFactory::class);
        $fileData = $factory->create($object);
        $object->setFileData($fileData);
        return $object;
    }

    public function supportsTransformation($data, array $context = []): bool
    {
        return $data instanceof FileInterface;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . FileDataFactory::class
        ];
    }
}
