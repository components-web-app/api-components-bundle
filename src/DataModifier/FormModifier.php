<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Form\FormViewFactory;

class FormModifier extends AbstractModifier
{
    /**
     * @param Form $form
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($form, array $context = array(), ?string $format = null)
    {
        /** @var FormViewFactory $factory */
        $factory = $this->container->get(FormViewFactory::class);
        $form->setForm($factory->create($form));
    }

    public function supportsData($data): bool
    {
        return $data instanceof Form && !$data->getForm();
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . FormViewFactory::class
        ];
    }
}
