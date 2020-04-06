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

namespace Silverback\ApiComponentBundle\Action\File;

use Silverback\ApiComponentBundle\Action\AbstractAction;
use Silverback\ApiComponentBundle\File\FileRequestHandler;
use Silverback\ApiComponentBundle\Serializer\RequestFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

class FileAction extends AbstractAction
{
    private FileRequestHandler $fileRequestHandler;

    public function __construct(SerializerInterface $serializer, RequestFormatResolver $requestFormatResolver, FileRequestHandler $fileRequestHandler)
    {
        parent::__construct($serializer, $requestFormatResolver);
        $this->fileRequestHandler = $fileRequestHandler;
    }

    public function __invoke(Request $request, string $field, string $id)
    {
        $response = $this->fileRequestHandler->handle($request, $this->requestFormatResolver->getFormatFromRequest($request), $field, $id);

        return $this->getResponse($request, $response->getContent(), $response->getStatusCode());
    }
}
