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

namespace Silverback\ApiComponentsBundle\Action;

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractAction
{
    /**
     * @var DecoderInterface|SerializerInterface
     */
    protected SerializerInterface $serializer;
    protected SerializeFormatResolver $requestFormatResolver;
    protected ResponseFactory $responseFactory;

    public function __construct(SerializerInterface $serializer, SerializeFormatResolver $requestFormatResolver, ResponseFactory $responseFactory)
    {
        if (!$serializer instanceof DecoderInterface) {
            throw new InvalidArgumentException(sprintf('The serializer injected into %s should implement %s', __CLASS__, DecoderInterface::class));
        }
        $this->serializer = $serializer;
        $this->requestFormatResolver = $requestFormatResolver;
        $this->responseFactory = $responseFactory;
    }
}
