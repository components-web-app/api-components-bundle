<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

class ComponentSubscriber implements EntitySubscriberInterface
{
    public function supportsEntity($entity = null): bool
    {
        return $entity instanceof AbstractComponent;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove
        ];
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     * @param AbstractComponent $component
     */
    public function preRemove(LifecycleEventArgs $eventArgs, AbstractComponent $component): void
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
