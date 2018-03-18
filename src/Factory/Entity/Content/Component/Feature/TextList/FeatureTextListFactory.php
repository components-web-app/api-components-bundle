<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeatureTextListFactory extends AbstractComponentFactory
{
    /** @var FeatureTextListItemFactory  */
    private $itemFactory;

    public function __construct(ObjectManager $manager, ValidatorInterface $validator, FeatureTextListItemFactory $itemFactory)
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
    public function create(?array $ops = null): FeatureTextList
    {
        $component = new FeatureTextList();
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
                'title' => null,
                'columns' => 3
            ]
        );
    }
}
