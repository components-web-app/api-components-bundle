<?php

namespace Silverback\ApiComponentBundle\Serializer\Middleware;

use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Factory\FileDataFactory;

class FileMiddleware extends AbstractMiddleware
{
    public function process($component, array $context = array())
    {
        /** @var FileDataFactory $factory */
        $factory = $this->container->get(FileDataFactory::class);
        $fileData = $factory->create($component);
        $component->setFileData($fileData);
    }

    public function supportsData($data): bool
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
