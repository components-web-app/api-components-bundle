<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory\Form;

use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;

class FormViewFactory
{
    /**
     * @var FormFactory
     */
    private $formFactory;
    public function __construct(
        FormFactory $formFactory
    ) {
        $this->formFactory = $formFactory;
    }
    /**
     * @param Form $component
     * @return FormView
     */
    public function create(Form $component): FormView
    {
        $form = $this->formFactory->create($component);
        $formForm = $form->getForm();
        return new FormView($formForm->createView(), $formForm);
    }
}
