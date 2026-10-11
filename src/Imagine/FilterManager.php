<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Imagine;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterManager as BaseFilterManager;

class FilterManager extends BaseFilterManager
{
    public function apply(BinaryInterface $binary, array $config): BinaryInterface
    {
        $format = ImagineOutputFormat::forFilter($config, $binary->getMimeType());
        if (null !== $format) {
            $config['format'] = $format;
        }

        return parent::apply($binary, $config);
    }
}
