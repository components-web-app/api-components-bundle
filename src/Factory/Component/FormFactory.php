<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;

class FormFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new Form();
    }

    public static function defaultOps(): array
    {
        return array_merge(parent::defaultOps(), [
            'formType' => null,
            'successHandler' => null
        ]);
    }

    public function create($owner, array $ops = null): AbstractComponent
    {
        /**
         * @var Form $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        $component->setFormType($ops['formType']);
        $component->setSuccessHandler($ops['successHandler']);
        return $component;
    }
}
