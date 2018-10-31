<?php

namespace Silverback\ApiComponentBundle\Cache;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Event\CommandNotifyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class FormCacheClearer implements CacheClearerInterface
{
    public const FORM_CACHE_EVENT_NAME = 'api_component_bundle.form_cache_clear_event';
    private $em;
    private $dispatcher;

    public function __construct(EntityManagerInterface $em, EventDispatcherInterface $dispatcher)
    {
        $this->em = $em;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param string $cacheDir
     * @throws \ReflectionException
     */
    public function clear($cacheDir = null): void
    {
        try {
            $repo = $this->em->getRepository(Form::class);
            $forms = $repo->findAll();
        } catch (\Exception $exception) {
            $this->dispatcher->dispatch(
                self::FORM_CACHE_EVENT_NAME,
                new CommandNotifyEvent(sprintf('<error>Could not clear form cache: %s</error>', $exception->getMessage()))
            );
            return;
        }
        if (!\count($forms)) {
            $this->dispatcher->dispatch(
                self::FORM_CACHE_EVENT_NAME,
                new CommandNotifyEvent('<info>Skipping form component cache clear / timestamp updates - No forms components found</info>')
            );
            return;
        }
        foreach ($forms as $form) {
            $this->updateFormTimestamp($form);
        }
        $this->em->flush();
    }

    /**
     * @param Form $form
     * @throws \ReflectionException
     */
    private function updateFormTimestamp(Form $form): void
    {
        $formClass = $form->getFormType();
        $reflector = new \ReflectionClass($formClass);
        $dateTime = new \DateTime();
        $timestamp = filemtime($reflector->getFileName());

        $this->dispatcher->dispatch(
            self::FORM_CACHE_EVENT_NAME,
            new CommandNotifyEvent(sprintf('<info>Checking timestamp for %s</info>', $formClass))
        );

        if (!$form->getLastModified() || $timestamp !== $form->getLastModified()->getTimestamp()) {
            $dateTime->setTimestamp($timestamp);
            $form->setLastModified($dateTime);
            $this->dispatcher->dispatch(
                self::FORM_CACHE_EVENT_NAME,
                new CommandNotifyEvent('<comment>Updated timestamp</comment>')
            );
        }
    }
}
