<?php

namespace Silverback\ApiComponentBundle\Serializer\Middleware;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Form\FormViewFactory;

class FormMiddleware extends AbstractMiddleware
{
    /**
     * @param Form $form
     * @param array $context
     * @return object|void
     */
    public function process($form, array $context = array())
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
