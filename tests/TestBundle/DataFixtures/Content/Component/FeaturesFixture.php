<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList\FeatureTextListFactory;

class FeaturesFixture extends AbstractFixture
{
    /**
     * @var FeatureColumnsFactory
     */
    private $featureColumnsFactory;
    /**
     * @var FeatureStackedFactory
     */
    private $featureStackedFactory;
    /**
     * @var FeatureTextListFactory
     */
    private $featureTextListFactory;

    public function __construct(
        FeatureColumnsFactory $featureColumnsFactory,
        FeatureStackedFactory $featureStackedFactory,
        FeatureTextListFactory $featureTextListFactory
    ) {
        $this->featureColumnsFactory = $featureColumnsFactory;
        $this->featureStackedFactory = $featureStackedFactory;
        $this->featureTextListFactory = $featureTextListFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createFeatureColumns();
        $this->createFeatureStacked();
        $this->createFeatureTextList();

        $manager->flush();
    }

    private function createFeatureColumns()
    {
        return $this->featureColumnsFactory->create(
            [
                'columns' => 3,
                'title' => 'Column features title'
            ]
        );
    }

    private function createFeatureStacked()
    {
        return $this->featureStackedFactory->create(
            [
                'reverse' => true
            ]
        );
    }

    private function createFeatureTextList()
    {
        return $this->featureTextListFactory->create(
            [
                'title' => 'Text list features title'
            ]
        );
    }
}
