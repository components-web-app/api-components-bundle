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

namespace Silverback\ApiComponentsBundle\Helper\Form;

use Silverback\ApiComponentsBundle\Dto\FormView;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSubmitHelper
{
    private FormFactoryInterface $formFactory;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        FormFactoryInterface $formFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->formFactory = $formFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process(Form $form, array $data, bool $isPartialSubmit): Form
    {
        $builder = $this->formFactory->createBuilder($form->formType);
        $symfonyForm = $builder->getForm();
        $formData = $this->getRootData($symfonyForm, $data);
        $symfonyForm->submit($formData, !$isPartialSubmit);
        $form->formView = new FormView($symfonyForm);

        return $form;
    }

    public function handleSuccess(Form $form)
    {
        $event = new FormSuccessEvent($form);
        $this->eventDispatcher->dispatch($event);

        return $event->result;
    }

    private function getRootData(FormInterface $form, $content): array
    {
        if (!isset($content[$form->getName()])) {
            throw new BadRequestHttpException(sprintf('Form object key could not be found. Expected: <b>%s</b>: { "input_name": "input_value" }', $form->getName()));
        }

        return $content[$form->getName()];
    }
}
