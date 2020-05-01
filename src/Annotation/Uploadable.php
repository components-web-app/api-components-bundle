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

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class Uploadable
{
    /**
     * This field will be mapped to the database and will store information about the file
     * when it is being stored. This is when it must be saved for Imagine cache images
     * as we will not have reliable access to the file after this point. The ResolverInterface
     * that cache resolvers use do not resolve the Binary, only a path to the file or url
     * which may resolve the file in realtime. We will trigger the thumbnails to be store as they
     * aren saved... in future even better using event dispatchers: https://symfony.com/doc/2.0/bundles/LiipImagineBundle/resolve-cache-images-in-background.html.
     *
     * It is probably better not to read the file every time in any case. And nobody should be manually
     * changing any files that the web application is saving. So the data *should not* change.
     *
     * The primary file could read on each load to be sure the data is up to date and provide a
     * fallback to if the file has changed on the server. If it has, perhaps we also automatically
     * try to clear the imagine cache? We should look to clear imagine cache of a file when
     * a file is deleted as well.
     */
    public string $filesInfoField = 'filesInfo';
}
