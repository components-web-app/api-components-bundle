<?php

namespace Silverback\ApiComponentBundle\Factory\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;

class FormFactory
{
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param FormFactoryInterface $formFactory
     * @param RouterInterface $router
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        RouterInterface $router
    ) {
        $this->formFactory = $formFactory;
        $this->router = $router;
    }

    /**
     * @param Form $component
     * @return FormInterface
     */
    public function create(Form $component): FormInterface
    {
        return $this->formFactory->create(
            $component->getFormType(),
            null,
            [
                'method' => 'POST',
                'action' => $this->router->generate('silverback_api_component_form_submit', [
                    'id' => $component->getId()
                ])
            ]
        );
    }
}
