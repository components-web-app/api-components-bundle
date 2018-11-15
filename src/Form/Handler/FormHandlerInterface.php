<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FormHandlerInterface
{
    /**
     * @param Form $form
     * @param mixed $data
     * @param Request $request
     * @return null|Response
     */
    public function success(Form $form, $data, Request $request): ?Response;
}
