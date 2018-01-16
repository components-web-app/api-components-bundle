<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Psr\Log\LoggerInterface;

class ContactHandler implements FormHandlerInterface
{
    private $logger;

    public function __construct(
        LoggerInterface $logger
    )
    {
        $this->logger = $logger;
    }

    public function success(Form $form)
    {
        $this->logger->info('Form submitted', [
            'form' => $form->getClassName()
        ]);
    }
}
