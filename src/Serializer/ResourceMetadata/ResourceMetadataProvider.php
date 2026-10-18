<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\ResourceMetadata;

use Silverback\ApiComponentsBundle\Utility\CallerScopedStateInterface;
use Symfony\Contracts\Service\ResetInterface;

class ResourceMetadataProvider implements ResetInterface, CallerScopedStateInterface
{
    /**
     * @var \WeakMap<object, ResourceMetadata>
     */
    private \WeakMap $metadatas;

    public function __construct()
    {
        $this->metadatas = new \WeakMap();
    }

    public function findResourceMetadata(object $object): ResourceMetadata
    {
        return $this->metadatas[$object] ??= new ResourceMetadata();
    }

    public function resourceMetadataExists(object $object): bool
    {
        return isset($this->metadatas[$object]);
    }

    public function reset(): void
    {
        $this->metadatas = new \WeakMap();
    }

    public function isolate(): \Closure
    {
        $metadatas = $this->metadatas;
        $this->metadatas = new \WeakMap();

        return function () use ($metadatas): void {
            $this->metadatas = $metadatas;
        };
    }
}
