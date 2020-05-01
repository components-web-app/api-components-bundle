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

namespace Silverback\ApiComponentsBundle\Factory\Response;

use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResponseFactory
{
    private SerializerInterface $serializer;
    private SerializeFormatResolver $formatResolver;

    public function __construct(SerializerInterface $serializer, SerializeFormatResolver $formatResolver)
    {
        $this->serializer = $serializer;
        $this->formatResolver = $formatResolver;
    }

    public function create(Request $request, $response = null, ?int $status = null): Response
    {
        $headers = [
            'Content-Type' => sprintf('%s; charset=utf-8', $request->getMimeType($format = $this->formatResolver->getFormatFromRequest($request))),
            'Vary' => 'Accept',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'deny',
        ];
        if (!\is_string($response)) {
            $response = $this->serializer->serialize($response, $format, []);
        }

        return new Response(
            $response,
            $status ?? Response::HTTP_OK,
            $headers
        );
    }
}
