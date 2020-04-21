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

namespace Silverback\ApiComponentBundle\Action\Form;

use Silverback\ApiComponentBundle\Action\AbstractAction;
use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentBundle\Form\Handler\FormSubmitHandler;
use Silverback\ApiComponentBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormPostPatchAction extends AbstractAction
{
    private FormSubmitHandler $formSubmitHandler;

    public function __construct(SerializerInterface $serializer, SerializeFormatResolver $requestFormatResolver, ResponseFactory $responseFactory, FormSubmitHandler $formSubmitHandler)
    {
        parent::__construct($serializer, $requestFormatResolver, $responseFactory);
        $this->formSubmitHandler = $formSubmitHandler;
    }

    public function __invoke(Request $request, Form $data)
    {
        $decodedContent = $this->serializer->decode($request->getContent(), $this->requestFormatResolver->getFormatFromRequest($request), []);
        $isPatchRequest = Request::METHOD_PATCH === $request->getMethod();
        $response = $this->formSubmitHandler->handle($decodedContent, $isPatchRequest, $data, $this->requestFormatResolver->getFormatFromRequest($request));

        return $this->responseFactory->create($request, $response->getContent(), $response->getStatusCode());
    }
}
