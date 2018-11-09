<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Stacked\FeatureStacked;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeatureStackedFactory extends AbstractComponentFactory
{
    /** @var FeatureStackedItemFactory */
    private $itemFactory;

    public function __construct(ObjectManager $manager, ValidatorInterface $validator, FeatureStackedItemFactory $itemFactory)
    {
        $this->itemFactory = $itemFactory;
        parent::__construct($manager, $validator);
    }

    public function getItemFactory()
    {
        return $this->itemFactory;
    }

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureStacked
    {
        $component = new FeatureStacked();
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
            parent::defaultOps(),
            [
                'reverse' => false
            ]
        );
    }
}
