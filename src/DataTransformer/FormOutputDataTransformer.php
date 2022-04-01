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

namespace Silverback\ApiComponentsBundle\DataTransformer;

use ApiPlatform\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormOutputDataTransformer implements DataTransformerInterface
{
    private FormViewFactory $formViewFactory;

    public function __construct(FormViewFactory $formViewFactory)
    {
        $this->formViewFactory = $formViewFactory;
    }

    /**
     * @param Form $form
     */
    public function transform($form, string $to, array $context = []): Form
    {
        $form->formView = $this->formViewFactory->create($form);

        return $form;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof Form && Form::class === $to && !$data->formView;
    }
}
