<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action;

use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Form\Handler\FormSubmitHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormPostPatchAction
{
    protected FormSubmitHandler $formSubmitHandler;

    public function __construct(FormSubmitHandler $formSubmitHandler)
    {
        $this->formSubmitHandler = $formSubmitHandler;
    }

    public function __invoke(Request $request, Form $data)
    {
        $this->formSubmitHandler->handle($request, $data);
    }
}
