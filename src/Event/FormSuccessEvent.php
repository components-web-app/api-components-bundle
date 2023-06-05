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

namespace Silverback\ApiComponentsBundle\Event;

use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSuccessEvent extends Event
{
    private Form $form;

    public $result;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    public function getForm(): Form
    {
        return $this->form;
    }

    public function getFormData()
    {
        return $this->form->formView->getForm()->getData();
    }
}
