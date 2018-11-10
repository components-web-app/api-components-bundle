<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeatureColumnsFactory extends AbstractComponentFactory
{
    /** @var FeatureColumnsItemFactory */
    private $featureColumnsItemFactory;

    public function __construct(ObjectManager $manager, ValidatorInterface $validator, FeatureColumnsItemFactory $featureColumnsItemFactory)
    {
        $this->featureColumnsItemFactory = $featureColumnsItemFactory;
        parent::__construct($manager, $validator);
    }

    public function getItemFactory()
    {
        return $this->featureColumnsItemFactory;
    }

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureColumns
    {
        $component = new FeatureColumns();
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
                'title' => null
            ]
        );
    }
}
