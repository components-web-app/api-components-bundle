<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper;

use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RefererUrlResolver
{
    private const HEADERS = ['origin', 'referer'];
    private const HOST_PATTERN = '/\A(?:[a-z0-9-]+(?:\.[a-z0-9-]+)*|\[[0-9a-f:.]+\])\z/i';
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly array $allowedOrigins = [],
        private readonly ?string $defaultOrigin = null,
    ) {
    }

    public function getAbsoluteUrl(RelativeUrlPath $path): string
    {
        return $this->getOrigin() . $path->path;
    }

    public function getOrigin(): string
    {
        $defaultOrigin = $this->getDefaultOrigin();

        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return $defaultOrigin ?? throw new InvalidArgumentException('To generate an absolute URL to the referrer, there must be a valid master request');
        }

        try {
            return $this->getAllowedRequestOrigin($request);
        } catch (UnparseableRequestHeaderException $exception) {
            return $defaultOrigin ?? throw $exception;
        }
    }

    private function getAllowedRequestOrigin(Request $request): string
    {
        foreach (self::HEADERS as $headerName) {
            $value = $request->headers->get($headerName);
            if (null === $value) {
                continue;
            }

            $origin = $this->parseOrigin($value, $headerName);
            if (null !== $origin && $this->isAllowed($origin)) {
                return $origin;
            }

            throw new DisallowedRequestOriginException(\sprintf('The `%s` header is not an origin that links in emails may point to', $headerName));
        }

        throw new UnparseableRequestHeaderException('To generate an absolute URL to the referrer, the request must have a `origin` or `referer` header present');
    }

    public static function allowedOriginRegex(string $pattern): string
    {
        return '{\A(?:' . $pattern . ')\z}i';
    }

    public static function normaliseOrigin(string $value): ?string
    {
        $parts = parse_url($value);
        if (
            !\is_array($parts)
            || isset($parts['user'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !\in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            return null;
        }

        return self::buildOrigin($parts);
    }

    private function getDefaultOrigin(): ?string
    {
        if (null === $this->defaultOrigin || '' === $this->defaultOrigin) {
            return null;
        }

        return self::normaliseOrigin($this->defaultOrigin) ?? throw new InvalidArgumentException(\sprintf('The configured default origin for links in emails `%s` must be a scheme and host with an optional port', $this->defaultOrigin));
    }

    private function parseOrigin(string $value, string $headerName): ?string
    {
        $parts = parse_url($value) ?: [];

        if (!isset($parts['host'])) {
            throw new UnparseableRequestHeaderException(\sprintf('Could not extract `host` while parsing the `%s` header', $headerName));
        }

        if (!isset($parts['scheme'])) {
            throw new UnparseableRequestHeaderException(\sprintf('Could not extract `scheme` while parsing the `%s` header', $headerName));
        }

        return self::buildOrigin($parts);
    }

    /**
     * @param array{scheme?: string, host?: string, port?: int} $parts
     */
    private static function buildOrigin(array $parts): ?string
    {
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!isset(self::DEFAULT_PORTS[$scheme]) || 1 !== preg_match(self::HOST_PATTERN, $host)) {
            return null;
        }

        $origin = $scheme . '://' . $host;
        $port = $parts['port'] ?? null;
        if (null !== $port && self::DEFAULT_PORTS[$scheme] !== $port) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function isAllowed(string $origin): bool
    {
        foreach ($this->allowedOrigins as $pattern) {
            $matched = @preg_match(self::allowedOriginRegex($pattern), $origin);
            if (false === $matched) {
                throw new InvalidArgumentException(\sprintf('The allowed origin pattern `%s` for links in emails is not a valid regular expression', $pattern));
            }
            if (1 === $matched) {
                return true;
            }
        }

        return false;
    }
}
