<?php

namespace Silverback\ApiComponentBundle\EntityListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

class ComponentListener
{
    /**
     * @ORM\PreRemove()
     * @param AbstractComponent $component
     * @param LifecycleEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     */
    public function preRemove(AbstractComponent $component, LifecycleEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getEntityManager();
        if ($component->onDeleteCascade()) {
            $this->deleteSubComponents($component, $entityManager);
        }
    }
    /**
     * @param AbstractComponent $component
     * @param EntityManagerInterface $entityManager
     */
    private function deleteSubComponents(AbstractComponent $component, EntityManagerInterface $entityManager): void
    {
        foreach ($component->getComponentGroups() as $componentGroup) {
            $entityManager->remove($componentGroup);
            foreach ($componentGroup->getComponentLocations() as $componentLocation) {
                $component = $componentLocation->getComponent();
                $entityManager->remove($componentLocation);
                $entityManager->remove($component);
                $this->deleteSubComponents($component, $entityManager);
            }
        }
    }
}
