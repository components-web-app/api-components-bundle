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

namespace Silverback\ApiComponentBundle\Event;

use Silverback\ApiComponentBundle\Entity\Component\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormSuccessEvent extends Event
{
    private Form $formResource;
    private FormInterface $form;
    public $response = null;
    public array $serializerContext = [];

    public function __construct(Form $formResource, FormInterface $form, $response = null)
    {
        $this->formResource = $formResource;
        $this->form = $form;
        $this->response = $response;
    }

    public function getFormResource(): Form
    {
        return $this->formResource;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }
}
