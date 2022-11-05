<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Helper\Form;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Event\CommandLogEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
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
            $this->dispatcher->dispatch(new CommandLogEvent(sprintf('<error>Could not clear form cache: %s</error>', $exception->getMessage())));

            return;
        }
        if (!\count($forms)) {
            $this->dispatcher->dispatch(new CommandLogEvent('<info>Skipping form component cache clear / timestamp updates - No forms components found</info>'));

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
        $reflector = new \ReflectionClass($formClass);
        $dateTime = new \DateTime();
        $timestamp = filemtime($reflector->getFileName());

        $this->dispatcher->dispatch(new CommandLogEvent(sprintf('<info>Checking timestamp for %s</info>', $formClass)));

        if (!$form->modifiedAt || $timestamp !== $form->modifiedAt->getTimestamp()) {
            $dateTime->setTimestamp($timestamp);
            $form->modifiedAt = $dateTime;
            $this->dispatcher->dispatch(new CommandLogEvent('<comment>Updated timestamp</comment>'));
        }
    }
}
