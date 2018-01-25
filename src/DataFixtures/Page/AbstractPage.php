<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\Entity\Page;

/**
 * Class AbstractPage
 * @package App\DataFixtures\Page
 * @author Daniel West <daniel@silverback.is>
 * @property Page $entity
 */
abstract class AbstractPage extends AbstractFixture
{
    /**
     * @var bool
     */
    protected $flushed = false;

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        if ($this instanceof CustomEntityInterface) {
            $this->entity = $this->getEntity();
        } else {
            $this->entity = new Page();
        }
    }

    protected function flush ()
    {
        parent::flush();
        $this->flushed = true;
    }

    protected function redirectFrom (Page $redirectFrom)
    {
        if (!$this->flushed) {
            throw new \BadMethodCallException('You should only call the redirectFrom method after flushing');
        }
        $redirectFrom->getRoutes()->first()->setRedirect($this->entity->getRoutes()->first());
        $this->manager->flush();
    }
}
