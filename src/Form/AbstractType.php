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

namespace Silverback\ApiComponentsBundle\Form;

use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
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
        $view->vars[FormSubmitHelper::FORM_REALTIME_VALIDATE_DISABLED] = $options[FormSubmitHelper::FORM_REALTIME_VALIDATE_DISABLED] ?? false;
        $view->vars[FormSubmitHelper::FORM_API_DISABLED] = $options[FormSubmitHelper::FORM_API_DISABLED] ?? false;
        $view->vars[FormSubmitHelper::FORM_POST_APP_PROXY] = $options[FormSubmitHelper::FORM_POST_APP_PROXY] ?? null;
    }
}
