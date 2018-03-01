<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;

interface FormHandlerInterface
{
    /**
     * @param Form $form
     * @return mixed
     */
    public function success(Form $form);
}
