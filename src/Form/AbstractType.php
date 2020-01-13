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

namespace Silverback\ApiComponentBundle\Form;

use Symfony\Component\Form\AbstractType as BaseAbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractType extends BaseAbstractType implements FormTypeInterface
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['realtime_validate'] = $options['realtime_validate'] ?? true;
        $view->vars['api_request'] = $options['api_request'] ?? true;
        $view->vars['post_app_proxy'] = $options['post_app_proxy'] ?? null;
    }
}
