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

namespace Silverback\ApiComponentsBundle\Helper;

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RefererUrlResolver
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getAbsoluteUrl($path): string
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $request = $this->requestStack->getMasterRequest();
        if (!$request) {
            throw new InvalidArgumentException('To generate an absolute URL to the referrer, there must be a valid master request');
        }

        if (null !== ($origin = $request->headers->get('origin', null))) {
            return $this->concatPathToUrl($origin, $path, 'origin');
        }
        if (null !== ($referer = $request->headers->get('referer', null))) {
            return $this->concatPathToUrl($referer, $path, 'referer');
        }

        throw new UnparseableRequestHeaderException('To generate an absolute URL to the referrer, the request must have a `origin` or `referer` header present');
    }

    private function concatPathToUrl(string $origin, string $path, string $headerName): string
    {
        return $this->getUrlPrefix($origin, $headerName) . '/' . ltrim($path, '/');
    }

    private function getUrlPrefix(string $url, string $headerName): string
    {
        $defaults = [
            'host' => null,
            'scheme' => null,
            'port' => null,
        ];

        [
            'host' => $host,
            'scheme' => $scheme,
            'port' => $port
        ] = array_merge($defaults, parse_url($url) ?: []);

        if (null === $host) {
            throw new UnparseableRequestHeaderException(sprintf('Could not extract `host` while parsing the `%s` header', $headerName));
        }

        if (null === $scheme) {
            throw new UnparseableRequestHeaderException(sprintf('Could not extract `scheme` while parsing the `%s` header', $headerName));
        }

        $url = $scheme . '://' . $host;
        if ($port && (('https' === $scheme && 443 !== $port) || ('http' === $scheme && 80 !== $port))) {
            $url .= ':' . $port;
        }

        return $url;
    }
}
