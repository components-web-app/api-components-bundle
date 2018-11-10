<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\ApiPlatform;

use ApiPlatform\Core\EventListener\EventPriorities;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FormSubscriber extends AbstractSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['setForm', EventPriorities::PRE_SERIALIZE]
            ]
        ];
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . FormViewFactory::class
        ];
    }

    public function setForm(GetResponseForControllerResultEvent $event): void
    {
        $form = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$form instanceof Form || Request::METHOD_GET !== $method || $form->getForm()) {
            return;
        }

        /** @var FormViewFactory $factory */
        $factory = $this->container->get(FormViewFactory::class);
        $form->setForm($factory->create($form));
    }
}
