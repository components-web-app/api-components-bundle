<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Serializer\ResourceMetadata;

class ResourceMetadataProvider
{
    public array $metadatas = [];

    public function findResourceMetadata(object $object): ResourceMetadata
    {
        $hash = spl_object_id($object);
        if ($this->resourceMetadataExists($object)) {
            return $this->metadatas[$hash]['metadata'];
        }
        $this->metadatas[$hash] = [
            'resource' => $object,
            'metadata' => new ResourceMetadata()
        ];

        return $this->metadatas[$hash]['metadata'];
    }

    public function resourceMetadataExists(object $object): bool
    {
        $hash = spl_object_id($object);

        return isset($this->metadatas[$hash]);
    }

    public function getMetadatas(): array
    {
        return $this->metadatas;
    }
}
