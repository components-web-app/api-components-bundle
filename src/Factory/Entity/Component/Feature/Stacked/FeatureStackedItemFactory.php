<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\AbstractFeatureItemFactory;

class FeatureStackedItemFactory extends AbstractFeatureItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureStackedItem
    {
        $component = new FeatureStackedItem();
        $this->init($component, $ops);
        $component->setDescription($this->ops['description']);
        $component->setButtonText($this->ops['buttonText']);
        $component->setButtonClass($this->ops['buttonClass']);
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
                'description' => null,
                'buttonText' => null,
                'buttonClass' => null
            ]
        );
    }
}
