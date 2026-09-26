<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\EventListener\Api\FormApiEventListener;
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class FormSerializeStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private FormViewFactory $formViewFactory,
        private FormSubmitHelper $formSubmitHelper,
        private SerializeFormatResolverInterface $serializeFormatResolver,
        private DecoderInterface $decoder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Form) {
            $data->formView = $this->formViewFactory->create($data);

            $request = $context['request'] ?? null;
            if ($request instanceof Request && FormApiEventListener::isSubmitRequest($request)) {
                $data = $this->submit($request, $data);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function submit(Request $request, Form $form): mixed
    {
        $requestContent = $this->decoder->decode($request->getContent(), $this->serializeFormatResolver->getFormatFromRequest($request), []);
        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        $form = $this->formSubmitHelper->process($form, $requestContent, $isPatch);

        $result = $form;
        if (!$isPatch && $form->formView->getForm()->isValid()) {
            $result = $this->formSubmitHelper->handleSuccess($form) ?: $form;
        }

        if (!$result instanceof Response) {
            $request->attributes->set('data', $result);
        }

        return $result;
    }
}
