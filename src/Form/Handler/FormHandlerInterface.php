<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\Component\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface FormHandlerInterface
{
    public function success(Form $form, $data, Request $request);
}
