<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class FormFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Form
    {
        $component = new Form();
        $this->init($component, $ops);
        $component->setFormType($this->ops['formType']);
        $component->setSuccessHandler($this->ops['successHandler']);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'formType' => '',
                'successHandler' => null
            ]
        );
    }
}
