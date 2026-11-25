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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Silverback\ApiComponentsBundle\Utility\AllowedComponentUi;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ComponentUiAllowedInGroupsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ComponentGroupRepository $componentGroupRepository,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    /**
     * @param ComponentUiAllowedInGroups $constraint
     */
    public function validate(mixed $component, Constraint $constraint): void
    {
        if (!$component instanceof AbstractComponent) {
            return;
        }

        $manager = $this->registry->getManagerForClass($component::class);
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }

        $placed = $this->publishedComponent($manager, $component) ?? $component;
        $original = $manager->getUnitOfWork()->getOriginalEntityData($component);
        if (!$original) {
            if ($placed === $component) {
                return;
            }
            $original = ['uiComponent' => $placed->uiComponent, 'uiClassNames' => $placed->uiClassNames];
        }

        $uiChanged = $original['uiComponent'] !== $component->uiComponent;
        $stylesChanged = $original['uiClassNames'] !== $component->uiClassNames;
        if (!$uiChanged && !$stylesChanged) {
            return;
        }

        $iri = (string) $this->iriConverter->getIriFromResource($component::class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($component::class));
        foreach ($this->componentGroupRepository->findRestrictingUiOf($placed) as $group) {
            if ($uiChanged && !AllowedComponentUi::allowsUiComponent($group->allowedComponentUi, $iri, $component->uiComponent)) {
                $this->context->buildViolation($constraint->uiMessage)
                    ->atPath('uiComponent')
                    ->setParameter('{{ ui }}', AllowedComponentUi::describeUiComponent($component->uiComponent))
                    ->setParameter('{{ iri }}', $iri)
                    ->setParameter('{{ reference }}', (string) $group->reference)
                    ->addViolation();
            }

            $disallowedStyles = $stylesChanged ? AllowedComponentUi::disallowedStyles($group->allowedComponentUi, $iri, $component->uiClassNames) : [];
            if ($disallowedStyles) {
                $this->context->buildViolation($constraint->stylesMessage)
                    ->atPath('uiClassNames')
                    ->setParameter('{{ styles }}', implode(', ', $disallowedStyles))
                    ->setParameter('{{ iri }}', $iri)
                    ->setParameter('{{ reference }}', (string) $group->reference)
                    ->addViolation();
            }
        }
    }

    private function publishedComponent(EntityManagerInterface $manager, AbstractComponent $component): ?AbstractComponent
    {
        if (!$this->publishableAttributeReader->isConfigured($component)) {
            return null;
        }

        $associationName = $this->publishableAttributeReader->getConfiguration($component)->associationName;
        $published = $manager->getClassMetadata($component::class)->getFieldValue($component, $associationName);

        return $published instanceof AbstractComponent ? $published : null;
    }
}
