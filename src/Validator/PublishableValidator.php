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

use ApiPlatform\Validator\ValidatorInterface;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;

/**
 * Builds and add validation group for published resources.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableValidator implements ValidatorInterface
{
    public const PUBLISHED_KEY = 'published';

    private ValidatorInterface $decorated;
    private PublishableStatusChecker $publishableStatusChecker;

    public function __construct(ValidatorInterface $decorated, PublishableStatusChecker $publishableStatusChecker)
    {
        $this->decorated = $decorated;
        $this->publishableStatusChecker = $publishableStatusChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($data, array $context = []): void
    {
        if (
            \is_object($data) &&
            $this->publishableStatusChecker->getAnnotationReader()->isConfigured($data) &&
            ($this->publishableStatusChecker->hasPublicationDate($data) || isset($context[self::PUBLISHED_KEY]))
        ) {
            $groups = [(new \ReflectionClass(\get_class($data)))->getShortName() . ':published'];
            if (!empty($this->publishableStatusChecker->getAnnotationReader()->getConfiguration($data)->validationGroups)) {
                $groups = $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($data)->validationGroups;
            }
            $context['groups'] = array_merge($context['groups'] ?? ['Default'], $groups);
        }

        $this->decorated->validate($data, $context);
    }
}
