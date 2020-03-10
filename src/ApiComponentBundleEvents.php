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

namespace Silverback\ApiComponentBundle;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ApiComponentBundleEvents
{
    /**
     * The FORM_SUCCESS event occurs when a Form component has been submitted successfully.
     *
     * This event allows you to perform actions based on a successful form submission.
     *
     * @Event("Silverback\ApiComponentBundle\Event\FormSuccessEvent")
     */
    public const FORM_SUCCESS = 'api_component.form.success';

    /**
     * The PRE_SERIALIZE event occurs immediately before a resource is normalized.
     *
     * This event allows you to modify the resource before the API responds with the data.
     *
     * @Event("Silverback\ApiComponentBundle\Event\PreNormalizeEvent")
     */
    public const PRE_NORMALIZE = 'api_component.data.pre_normalize';
}
