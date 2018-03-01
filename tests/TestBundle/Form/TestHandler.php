<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\Form;

use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;

class TestHandler implements FormHandlerInterface
{
    public $info;

    public function success(Form $form)
    {
        $this->info = 'Form submitted';
    }
}
