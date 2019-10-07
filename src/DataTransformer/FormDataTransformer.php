<?php

namespace Silverback\ApiComponentBundle\DataTransformer;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Form\FormViewFactory;

final class FormDataTransformer extends AbstractDataTransformer
{
    /**
     * @param Form $object
     */
    public function transform($object, array $context = []): Form
    {
        /** @var FormViewFactory $factory */
        $factory = $this->container->get(FormViewFactory::class);
        $object->setForm($factory->create($object));
        return $object;
    }

    public function supportsTransformation($data, array $context = []): bool
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
