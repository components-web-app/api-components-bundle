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

use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSubmitHelper
{
    public const FORM_REALTIME_VALIDATE_DISABLED = 'realtime_validate_disabled';
    public const FORM_API_DISABLED = 'api_disabled';
    public const FORM_POST_APP_PROXY = 'post_app_proxy';
    private FormFactoryInterface $formFactory;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        FormFactoryInterface $formFactory,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->formFactory = $formFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process(Form $form, array $data, bool $isPartialSubmit): Form
    {
        $builder = $this->formFactory->createBuilder($form->formType);
        $symfonyForm = $builder->getForm();
        $config = $symfonyForm->getConfig();
        if (true === $config->getOption(self::FORM_API_DISABLED, false)) {
            throw new NotFoundHttpException();
        }

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
            throw new UnprocessableEntityHttpException(\sprintf('Form object key could not be found. Expected: <b>%s</b>: { "input_name": "input_value" }', $form->getName()));
        }

        return $content[$form->getName()];
    }
}
