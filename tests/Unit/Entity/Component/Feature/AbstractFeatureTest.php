<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Feature;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;

class AbstractFeatureTest extends TestCase
{
    public function test_constructor()
    {
        /** @var AbstractFeature $entity */
        $entity = $this->getMockForAbstractClass(AbstractFeature::class);
        $this->assertEquals(new ArrayCollection([ AbstractFeatureItem::class ]), $entity->getValidComponents());
        $this->assertCount(1, $entity->getComponentGroups());
    }
}
