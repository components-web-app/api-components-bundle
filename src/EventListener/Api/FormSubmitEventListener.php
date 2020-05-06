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

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSubmitEventListener
{
    private FormSubmitHelper $formSubmitHelper;
    private SerializeFormatResolver $serializeFormatResolver;
    private SerializerInterface $serializer;

    public function __construct(
        FormSubmitHelper $formSubmitHelper,
        SerializeFormatResolver $serializeFormatResolver,
        SerializerInterface $serializer
    ) {
        $this->formSubmitHelper = $formSubmitHelper;
        $this->serializeFormatResolver = $serializeFormatResolver;
        $this->serializer = $serializer;
    }

    public function onPreSerialize(ViewEvent $event): void
    {
        $request = $event->getRequest();
        if (!$data = $this->getData($request)) {
            return;
        }

        // Handle post/patch requests
        $format = $this->serializeFormatResolver->getFormatFromRequest($request);
        $requestContent = $this->serializer->decode($request->getContent(), $format, []);
        $data = $this->formSubmitHelper->process($data, $requestContent, Request::METHOD_PUT !== $request->getMethod());

        if ($data->formView->getForm()->isValid()) {
            $result = $this->formSubmitHelper->handleSuccess($data);
            if ($result) {
                $data = $result;
            }
        }

        $event->setControllerResult($data);
        $request->attributes->set('data', $data);
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ((!$data = $this->getData($request))) {
            return;
        }

        if ($formView = $data->formView) {
            $form = $formView->getForm();
            $response = $event->getResponse();
            if (!$form->isValid()) {
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            }
        }
    }

    private function getData(Request $request): ?Form
    {
        $postfix = '/submit';

        $data = $request->attributes->get('data');
        $method = $request->getMethod();
        if (
            empty($data) ||
            !$data instanceof Form ||
            !\in_array($method, [Request::METHOD_POST, Request::METHOD_PATCH], true) ||
            0 !== substr_compare($request->getPathInfo(), $postfix, -\strlen($postfix))
        ) {
            return null;
        }

        return $data;
    }
}
