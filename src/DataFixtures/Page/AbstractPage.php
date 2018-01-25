<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\ComponentAwareInterface;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\Entity\Page;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Class AbstractPage
 * @package App\DataFixtures\Page
 * @author Daniel West <daniel@silverback.is>
 * @property Page $entity
 */
abstract class AbstractPage extends AbstractFixture implements ComponentAwareInterface
{
    /**
     * @var ComponentServiceLocator
     */
    protected $serviceLocator;

    public function __construct(ComponentServiceLocator $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * @param ObjectManager $manager
     * @return Object
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        if ($this instanceof CustomEntityInterface) {
            $this->entity = $this->getEntity();
        } else {
            $this->entity = new Page();
        }
        return $this->entity;
    }

    protected function redirectFrom (Page $redirectFrom)
    {
        if (!$this->flushed) {
            throw new \BadMethodCallException('You should only call the redirectFrom method after flushing');
        }
        if (count($redirectFrom->getRoutes()) < 1) {
            throw new \InvalidArgumentException('The page you are redirecting from has no routes');
        }
        if (count($this->entity->getRoutes()) < 1) {
            throw new \InvalidArgumentException('This page does not have any routes to redirect to');
        }
        $redirectFrom->getRoutes()->first()->setRedirect($this->entity->getRoutes()->first());
        $this->manager->flush();
    }

    public function createComponent (string $componentService, $owner, array $ops = null)
    {
        $service = $this->serviceLocator->get($componentService);
        $service->load($this->manager);
        return $service->create($owner, $ops);
    }
}
