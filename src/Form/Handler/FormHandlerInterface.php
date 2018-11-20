<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

interface FormHandlerInterface
{
    /**
     * @param Form $form
     * @param mixed $data
     * @param Request $request
     * @return mixed
     */
    public function success(Form $form, $data, Request $request);
}
