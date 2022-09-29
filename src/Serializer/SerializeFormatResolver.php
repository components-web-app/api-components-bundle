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

namespace Silverback\ApiComponentsBundle\Serializer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class SerializeFormatResolver implements SerializeFormatResolverInterface
{
    private RequestStack $requestStack;
    private string $defaultFormat;

    public function __construct(RequestStack $requestStack, string $defaultFormat = 'jsonld')
    {
        $this->requestStack = $requestStack;
        $this->defaultFormat = $defaultFormat;
    }

    public function getFormat(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return $this->defaultFormat;
        }

        return $this->getFormatFromRequest($request);
    }

    public function getFormatFromRequest(Request $request): string
    {
        // Symfony 6.2 deprecated getContentType in favor of getContentTypeFormat
        $contentTypeMethod = method_exists($request, 'getContentTypeFormat') ? 'getContentTypeFormat' : 'getContentType';

        return $request->getRequestFormat(null) ?: $request->{$contentTypeMethod}() ?: $this->defaultFormat;
    }
}
