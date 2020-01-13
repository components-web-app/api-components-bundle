<?php

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Form\Handler\ContextProviderInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TestHandler implements FormHandlerInterface, ContextProviderInterface
{
    public $info;

    public function success(Form $form, $data, Request $request): ?Response
    {
        $this->info = 'Form submitted';
        return null;
    }

    public function getContext(): ?array
    {
        return null;
    }
}
