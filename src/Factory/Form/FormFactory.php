<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

class FormFactory
{
    private $formFactory;
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
     * @return FormBuilderInterface
     */
    public function create(Form $component): FormBuilderInterface
    {
        $builder = $this->formFactory->createBuilder($component->getFormType());
        if (!($currentAction = $builder->getAction()) || $currentAction === '') {
            $action = $this->router->generate(
                'api_forms_post_item',
                [
                    'id' => $component->getId()
                ]
            );
            $builder->setAction($action);
        }
        return $builder;
    }
}
