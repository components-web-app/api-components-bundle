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
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormApiEventListener
{
    private FormSubmitHelper $formSubmitHelper;
    private SerializeFormatResolver $serializeFormatResolver;
    private SerializerInterface $serializer;
    private FormViewFactory $formViewFactory;

    public function __construct(
        FormSubmitHelper $formSubmitHelper,
        SerializeFormatResolver $serializeFormatResolver,
        SerializerInterface $serializer,
        FormViewFactory $formViewFactory
    ) {
        if (!$serializer instanceof DecoderInterface) {
            throw new \InvalidArgumentException(sprintf('$serializer must be also be an instance of %s', DecoderInterface::class));
        }
        $this->formSubmitHelper = $formSubmitHelper;
        $this->serializeFormatResolver = $serializeFormatResolver;
        $this->serializer = $serializer;
        $this->formViewFactory = $formViewFactory;
    }

    public function onPreSerialize(ViewEvent $event): void
    {
        $this->decorateOutput($event);
        $this->handleFormData($event);
    }

    private function handleFormData(ViewEvent $event): void
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
                // we were going to do sub-requests, but then we may require authorization and for forms we shouldn't need that
                // instead Form:component:read serialization group should be added to properties of objects being returned
                // for them to be serialized in the result
                $data = $result;
            }
        }

        $event->setControllerResult($data);
        if (!$data instanceof Response) {
            $request->attributes->set('data', $data);
        }
    }

    private function decorateOutput(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$data instanceof Form
        ) {
            return;
        }
        $data->formView = $this->formViewFactory->create($data);
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$data = $this->getData($request)) {
            return;
        }

        if ($formView = $data->formView) {
            $form = $formView->getForm();
            $response = $event->getResponse();
            if (!$form->isValid()) {
                $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
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
            !\in_array($method, [Request::METHOD_POST, Request::METHOD_PATCH, Request::METHOD_PUT], true) ||
            0 !== substr_compare($request->getPathInfo(), $postfix, -\strlen($postfix))
        ) {
            return null;
        }

        return $data;
    }
}
