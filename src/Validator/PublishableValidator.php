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

namespace Silverback\ApiComponentsBundle\Validator;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableHelper;

/**
 * Builds and add validation group for published resources.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableValidator implements ValidatorInterface
{
    public const PUBLISHED_KEY = 'published';

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
    public function validate($data, array $context = []): void
    {
        if (
            \is_object($data) &&
            $this->publishableHelper->getAnnotationReader()->isConfigured($data) &&
            ($this->publishableHelper->hasPublicationDate($data) || isset($context[self::PUBLISHED_KEY]))
        ) {
            $groups = [(new \ReflectionClass(\get_class($data)))->getShortName() . ':published'];
            if (!empty($this->publishableHelper->getAnnotationReader()->getConfiguration($data)->validationGroups)) {
                $groups = $this->publishableHelper->getAnnotationReader()->getConfiguration($data)->validationGroups;
            }
            $context['groups'] = array_merge($context['groups'] ?? ['Default'], $groups);
        }

        $this->decorated->validate($data, $context);
    }
}
