<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Factory\File\FileDataFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FileOutputDataTransformer implements DataTransformerInterface
{
    private FileDataFactory $fileDataFactory;

    public function __construct(FileDataFactory $fileDataFactory)
    {
        $this->fileDataFactory = $fileDataFactory;
    }

    /**
     * @param FileInterface $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $object->setFileData($this->fileDataFactory->create($object));

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof FileInterface;
    }
}
