<?php

namespace Silverback\ApiComponentBundle\Tests\src\Form;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;

class TestHandler implements FormHandlerInterface
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
        $this->logger->info('Form submitted');
    }
}
