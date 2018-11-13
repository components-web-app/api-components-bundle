<?php

declare(strict_types=1);

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
                'action' => $this->router->generate(
                    'api_forms_post_item',
                    [
                        'id' => $component->getId()
                    ]
                )
            ]
        );
    }
}
