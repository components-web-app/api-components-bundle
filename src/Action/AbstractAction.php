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

namespace Silverback\ApiComponentBundle\Action;

use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Silverback\ApiComponentBundle\Serializer\RequestFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractAction
{
    protected DecoderInterface $serializer;
    protected RequestFormatResolver $requestFormatResolver;

    public function __construct(SerializerInterface $serializer, RequestFormatResolver $requestFormatResolver)
    {
        if (!$serializer instanceof DecoderInterface) {
            throw new InvalidParameterException(sprintf('The serializer injected into %s should implement %s', __CLASS__, DecoderInterface::class));
        }
        $this->serializer = $serializer;
        $this->requestFormatResolver = $requestFormatResolver;
    }

    protected function getFormat(Request $request): string
    {
        return $this->requestFormatResolver->getFormatFromRequest($request);
    }

    protected function getResponse(Request $request, $response = null, ?int $status = null): Response
    {
        $headers = [
            'Content-Type' => sprintf('%s; charset=utf-8', $format = $this->getFormat($request)),
            'Vary' => 'Accept',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'deny',
        ];
        if (!\is_string($response)) {
            $response = $this->serializer->serialize($response, $format, []);
        }
        new Response(
            $response,
            $status ?? Response::HTTP_OK,
            $headers
        );
    }
}
