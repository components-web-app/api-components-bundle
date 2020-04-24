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

namespace Silverback\ApiComponentBundle\Validator;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;

/**
 * Builds and add validation group for published resources.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableValidator implements ValidatorInterface
{
    private ValidatorInterface $decorated;
    private PublishableHelper $publishableHelper;

    public function __construct(ValidatorInterface $decorated, PublishableHelper $publishableHelper)
    {
        $this->decorated = $decorated;
        $this->publishableHelper = $publishableHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($data, array $context = [])
    {
        if (is_object($data) && $this->publishableHelper->isPublishable($data) && $this->publishableHelper->hasPublicationDate($data)) {
            $groups = $context['groups'] ?? ['Default'];
            $groups[] = (new \ReflectionClass(get_class($data)))->getShortName().':published';
            $context['groups'] = $groups;
        }

        $this->decorated->validate($data, $context);
    }
}