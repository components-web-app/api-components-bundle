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

namespace Silverback\ApiComponentsBundle\Form\Handler;

use Silverback\ApiComponentsBundle\Dto\FormView;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Factory\Form\FormFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSubmitHandler
{
    private FormFactory $formFactory;
    private EventDispatcherInterface $eventDispatcher;
    private SerializerInterface $serializer;

    public function __construct(FormFactory $formFactory, EventDispatcherInterface $eventDispatcher, SerializerInterface $serializer)
    {
        $this->formFactory = $formFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
    }

    public function handle(array $decodedContent, bool $isPatchRequest, Form $formResource, string $_format): Response
    {
        $builder = $this->formFactory->create($formResource);
        $form = $builder->getForm();
        $formData = $this->validateDecodedContent($form, $decodedContent);
        $form->submit($formData, !$isPatchRequest);
        if ($isPatchRequest) {
            return $this->handlePatch($formResource, $form, $_format, $formData);
        }

        return $this->handlePost($formResource, $form, $_format);
    }

    private function handlePost(Form $formResource, FormInterface $form, string $_format): Response
    {
        $valid = $form->isValid();
        $formResource->formView = new FormView($form);
        $context = [];
        $response = $formResource;
        if ($valid) {
            $event = new FormSuccessEvent($formResource, $form, $response);
            $this->eventDispatcher->dispatch($event);
            $response = $event->response;
            $context = $event->serializerContext;
        }

        return $this->getResponse($response, $_format, $valid, $context);
    }

    private function handlePatch(Form $formResource, FormInterface $form, string $_format, array $formData): Response
    {
        $dataCount = \count($formData);
        if (!$dataCount) {
            return $this->getResponse($formResource, $_format, true);
        }

        if (1 === $dataCount) {
            $formData = [$formData];
        }

        return $this->getPatchValidationResponse($formResource, $form, $_format, $formData);
    }

    private function getPatchValidationResponse(Form $formResource, FormInterface $form, string $_format, array $formData): Response
    {
        $formResources = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $formResource;
            $data = \is_string($formData[$key]) ? [$key => $formData[$key]] : $formData[$key];
            $formItem = $this->getChildFormByKey($form, $data);
            $dataItem->formView = $formView = new FormView($formItem);
            $formResources[] = $dataItem;
            if ($valid && !self::isFormViewValid($formView)) {
                $valid = false;
            }
        }

        return $this->getResponse($formResources, $_format, $valid);
    }

    private static function isFormViewValid(FormView $formView): bool
    {
        return $formView->getVars()['valid'];
    }

    private function validateDecodedContent(FormInterface $form, $content): array
    {
        if (!isset($content[$form->getName()])) {
            throw new BadRequestHttpException(sprintf('Form object key could not be found. Expected: <b>%s</b>: { "input_name": "input_value" }', $form->getName()));
        }

        return $content[$form->getName()];
    }

    private function getChildFormByKey(FormInterface $form, array $formData): FormInterface
    {
        $child = $form->get($key = key($formData));
        while ($this->isSequentialStringsArray($formData = $formData[$key]) && $count = \count($formData)) {
            if (1 === $count) {
                $child = $child->get((string) $key = key($formData));
                continue;
            }
            // front-end should submit empty objects for each item in a collection up to the one we are trying to validate
            // so let us just get the last item to validate
            // key should be numeric, if not it is probably first and second for repeated field. These should both be checked...
            $key = (string) ($count - 1);
            if (!$child->has($key)) {
                break;
            }
            $child = $child->get($key);
        }

        return $child;
    }

    private function isSequentialStringsArray($data): bool
    {
        return \is_array($data) && !$this->isAssocArray($data) && $this->arrayIsStrings($data);
    }

    private function isAssocArray(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, \count($arr) - 1);
    }

    private function arrayIsStrings(array $arr): bool
    {
        foreach ($arr as $item) {
            if (!\is_string($item)) {
                return false;
            }
        }

        return true;
    }

    private function getResponse(
        $data,
        string $_format,
        bool $valid,
        array $context = []
    ): Response {
        $response = new Response();
        $response->setStatusCode($valid ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
        $response->setContent($this->serializer->serialize($data, $_format, $context));

        return $response;
    }
}
