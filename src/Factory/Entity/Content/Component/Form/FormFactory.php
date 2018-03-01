<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form;

use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class FormFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Form
    {
        $component = new Form();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            AbstractFactory::COMPONENT_CLASSES,
            [
                'formType' => '',
                'successHandler' => null
            ]
        );
    }
}
