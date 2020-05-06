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

namespace Silverback\ApiComponentsBundle\Factory\Form;

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\Dto\FormView;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\UrlHelper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormViewFactory
{
    private FormFactoryInterface $formFactory;
    private IriConverterInterface $iriConverter;
    private UrlHelper $urlHelper;

    public function __construct(FormFactoryInterface $formFactory, IriConverterInterface $iriConverter, UrlHelper $urlHelper)
    {
        $this->formFactory = $formFactory;
        $this->iriConverter = $iriConverter;
        $this->urlHelper = $urlHelper;
    }

    public function create(Form $form): FormView
    {
        $builder = $this->formFactory->createBuilder($form->formType);

        if (!($currentAction = $builder->getAction()) || '' === $currentAction) {
            $builder->setAction($this->getFormAction($form));
        }

        return new FormView($builder->getForm());
    }

    private function getFormAction(Form $form): string
    {
        return $this->urlHelper->getAbsoluteUrl($this->iriConverter->getIriFromItem($form) . '/submit');
    }
}
