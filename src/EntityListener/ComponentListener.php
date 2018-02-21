<?php

namespace Silverback\ApiComponentBundle\EntityListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

class ComponentListener
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @ORM\PreRemove()
     * @param AbstractComponent $component
     * @param LifecycleEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     */
    public function preRemove(AbstractComponent $component, LifecycleEventArgs $eventArgs): void
    {
        $this->em = $eventArgs->getEntityManager();
        if ($component->onDeleteCascade()) {
            $this->deleteSubComponents($component);
        }
    }

    /**
     * @param AbstractComponent $component
     */
    private function deleteSubComponents(AbstractComponent $component): void
    {
        foreach ($component->getComponentGroups() as $componentGroup) {
            $this->em->remove($componentGroup);
            foreach ($componentGroup->getComponents() as $componentLocation) {
                $component = $componentLocation->getComponent();
                $this->em->remove($componentLocation);
                $this->em->remove($component);
                $this->deleteSubComponents($component);
            }
        }
    }
}
