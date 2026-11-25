<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class AllowedComponentUiValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    /**
     * @param array<mixed>|null  $value
     * @param AllowedComponentUi $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        $componentIris = $this->componentCollectionIris();
        foreach ($value as $key => $rule) {
            if (!\in_array($key, $componentIris, true)) {
                $this->addViolation($constraint->keyMessage, $key);

                continue;
            }

            if (!\is_array($rule) || [] === $rule || [] !== array_diff(array_keys($rule), ['ui', 'styles'])) {
                $this->addViolation($constraint->ruleMessage, $key);

                continue;
            }

            if (\array_key_exists('ui', $rule) && !self::isNonEmptyListOfStrings($rule['ui'], true)) {
                $this->addViolation($constraint->uiMessage, $key);
            }

            if (\array_key_exists('styles', $rule) && !self::isNonEmptyListOfStrings($rule['styles'], false)) {
                $this->addViolation($constraint->stylesMessage, $key);
            }
        }
    }

    private function addViolation(string $message, int|string $key): void
    {
        $this->context->buildViolation($message)
            ->setParameter('{{ key }}', (string) $key)
            ->addViolation();
    }

    /**
     * @return list<string>
     */
    private function componentCollectionIris(): array
    {
        $iris = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            if (!is_subclass_of($resourceClass, AbstractComponent::class) || (new \ReflectionClass($resourceClass))->isAbstract()) {
                continue;
            }
            try {
                $iris[] = (string) $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
            } catch (ExceptionInterface) {
            }
        }

        return $iris;
    }

    private static function isNonEmptyListOfStrings(mixed $values, bool $nullable): bool
    {
        if (!\is_array($values) || [] === $values || !array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!\is_string($value) && !($nullable && null === $value)) {
                return false;
            }
        }

        return true;
    }
}
