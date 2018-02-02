<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\ComponentAwareInterface;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\Entity\Content\Page;

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

    /**
     * @param Page $redirectFrom
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function redirectFrom(Page $redirectFrom)
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

    /**
     * @param string $componentService
     * @param null $owner
     * @param array|null $ops
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createComponent(string $componentService, $owner = null, array $ops = null)
    {
        if (!$owner) {
            $owner = $this->entity;
        }
        $service = $this->serviceLocator->get($componentService);
        return $service->create($owner, $ops);
    }
}
