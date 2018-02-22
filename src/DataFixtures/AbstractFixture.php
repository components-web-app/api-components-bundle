<?php

namespace Silverback\ApiComponentBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

abstract class AbstractFixture extends Fixture
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var bool
     */
    protected $flushed = false;

    protected $entity;

    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;
    }

    protected function flush()
    {
        if (!$this->entity) {
            throw new \BadMethodCallException('You must set the entity variable to persist');
        }
        $this->manager->persist($this->entity);
        $this->manager->flush();

        $this->flushed = true;
    }
}
