<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form;

use Symfony\Component\Form\AbstractType as BaseAbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class AbstractType extends BaseAbstractType implements FormTypeInterface
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['realtime_validate'] = $options['realtime_validate'] ?? true;
        $view->vars['api_request'] = $options['api_request'] ?? true;
    }
}
