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

namespace Silverback\ApiComponentBundle\Form\Handler;

use InvalidArgumentException;
use JsonException;
use Silverback\ApiComponentBundle\Dto\Form;
use Silverback\ApiComponentBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentBundle\Factory\FormFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use function json_decode;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSubmitHandler
{
    private FormFactory $formFactory;
    private EventDispatcher $eventDispatcher;
    private SerializerInterface $serializer;

    public function __construct(FormFactory $formFactory, EventDispatcher $eventDispatcher, SerializerInterface $serializer)
    {
        $this->formFactory = $formFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
    }

    public function handle(Request $request, Form $formResource, string $_format): Response
    {
        $builder = $this->formFactory->create($formResource);
        $form = $builder->getForm();
        $formData = $this->deserializeFormData($form, $request->getContent());
        $isPatchRequest = Request::METHOD_PATCH === $request->getMethod();
        $form->submit($formData, !$isPatchRequest);
        if ($isPatchRequest) {
            return $this->handlePatch($formResource, $form, $_format, $formData);
        }

        return $this->handlePost($formResource, $form, $_format);
    }

    private function handlePatch(Form $formResource, FormInterface $form, string $_format, array $formData): Response
    {
        $isFormViewValid = static function (Form $formView): bool {
            return $formView->getVars()['valid'];
        };

        $dataCount = \count($formData);
        if (1 === $dataCount) {
            $formItem = $this->getChildFormByKey($form, $formData);
            $formResource->form = $formView = new Form($formItem);

            return $this->getResponse($formResource, $_format, $isFormViewValid($formView));
        }

        $formResources = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $formResource;
            $formItem = $this->getChildFormByKey($form, $formData[$key]);
            $dataItem->form = $formView = new Form($formItem);
            $formResources[] = $dataItem;
            if ($valid && !$isFormViewValid($formView)) {
                $valid = false;
            }
        }

        return $this->getResponse($formResources, $_format, $valid);
    }

    private function handlePost(Form $formResource, FormInterface $form, string $_format): Response
    {
        $valid = $form->isValid();
        $formResource->form = new Form($form);
        $context = [];
        if ($valid) {
            $event = new FormSuccessEvent($formResource, $form);
            $this->eventDispatcher->dispatch($event);
            $context = $event->serializerContext;
        }

        return $this->getResponse($formResource, $_format, $valid, $context);
    }

    private function deserializeFormData(FormInterface $form, $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR | 0);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('json_decode error: ' . $exception->getMessage());
        }
        if (!isset($decoded[$form->getName()])) {
            throw new BadRequestHttpException(sprintf('Form object key could not be found. Expected: <b>%s</b>: { "input_name": "input_value" }', $form->getName()));
        }

        return $decoded[$form->getName()];
    }

    private function getChildFormByKey(FormInterface $form, array $formData): FormInterface
    {
        $child = $form->get($key = key($formData));
        while ($this->isSequentialStringsArray($formData = $formData[$key]) && $count = \count($formData)) {
            if (1 === $count) {
                $child = $child->get($key = key($formData));
                continue;
            }
            // front-end should submit empty objects for each item in a collection up to the one we are trying to validate
            // so let us just get the last item to validate
            // key should be numeric, if not it is probably first and second for repeated field. These should both be checked...
            $key = ($count - 1);
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
