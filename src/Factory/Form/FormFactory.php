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

use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormFactory
{
    private FormFactoryInterface $formFactory;
    private RouterInterface $router;

    public function __construct(
        FormFactoryInterface $formFactory,
        RouterInterface $router
    ) {
        $this->formFactory = $formFactory;
        $this->router = $router;
    }

    public function create(Form $component): FormBuilderInterface
    {
        $builder = $this->formFactory->createBuilder($component->formType);
        if (!($currentAction = $builder->getAction()) || '' === $currentAction) {
            // Should we not be looking for the POST endpoint for the resource from API Platform instead of assuming this will be the name?
            $action = $this->router->generate(
                'api_forms_post_item',
                [
                    'id' => $component->getId(),
                ]
            );
            $builder->setAction($action);
        }

        return $builder;
    }
}
