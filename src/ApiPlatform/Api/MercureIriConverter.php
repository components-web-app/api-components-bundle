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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Api;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MercureIriConverter implements IriConverterInterface
{
    public function __construct(private IriConverterInterface $decorated, private PublishableStatusChecker $publishableStatusChecker)
    {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    public function getIriFromResource($resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        $iri = $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);

        if (\is_string($resource)) {
            return $iri;
        }

        if ($this->publishableStatusChecker->getAttributeReader()->isConfigured($resource) && !$this->publishableStatusChecker->isActivePublishedAt($resource)) {
            $iri .= '?draft=1';
        }

        return $iri;
    }
}
