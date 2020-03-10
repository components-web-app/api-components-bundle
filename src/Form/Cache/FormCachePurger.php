<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Cache;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Silverback\ApiComponentBundle\ApiComponentBundleEvents;
use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Event\CommandLogEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class FormCachePurger implements CacheClearerInterface
{
    private EntityManagerInterface $em;
    private EventDispatcherInterface $dispatcher;

    public function __construct(EntityManagerInterface $em, EventDispatcherInterface $dispatcher)
    {
        $this->em = $em;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param string $cacheDir
     */
    public function clear($cacheDir = null): void
    {
        try {
            $repo = $this->em->getRepository(Form::class);
            /** @var Form[] $forms */
            $forms = $repo->findAll();
        } catch (\Exception $exception) {
            $this->dispatcher->dispatch(
                new CommandLogEvent(sprintf('<error>Could not clear form cache: %s</error>', $exception->getMessage())),
                ApiComponentBundleEvents::COMMAND_LOG
            );
            return;
        }
        if (!\count($forms)) {
            $this->dispatcher->dispatch(
                new CommandLogEvent('<info>Skipping form component cache clear / timestamp updates - No forms components found</info>'),
                ApiComponentBundleEvents::COMMAND_LOG
            );
            return;
        }
        foreach ($forms as $form) {
            $this->updateFormTimestamp($form);
        }
        $this->em->flush();
    }

    private function updateFormTimestamp(Form $form): void
    {
        $formClass = $form->formType;
        $reflector = new ReflectionClass($formClass);
        $dateTime = new DateTime();
        $timestamp = filemtime($reflector->getFileName());

        $this->dispatcher->dispatch(
            new CommandLogEvent(sprintf('<info>Checking timestamp for %s</info>', $formClass)),
            ApiComponentBundleEvents::COMMAND_LOG
        );

        if (!$form->modified || $timestamp !== $form->modified->getTimestamp()) {
            $dateTime->setTimestamp($timestamp);
            $form->modified = $dateTime;
            $this->dispatcher->dispatch(
                new CommandLogEvent('<comment>Updated timestamp</comment>'),
                ApiComponentBundleEvents::COMMAND_LOG
            );
        }
    }
}
