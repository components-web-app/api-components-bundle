<?php

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\Route;
use Symfony\Component\HttpFoundation\Request;

class ApiContextBuilder implements SerializerContextBuilderInterface
{
    /**
     * @var string[][]
     */
    public const CLASS_GROUP_MAPPING = [
        AbstractComponent::class => ['component'],
        AbstractNavigation::class => ['component'],
        ComponentLocation::class => ['component'],
        AbstractNavigationItem::class => ['component_item'],
        AbstractContent::class => ['content'],
        Route::class => ['route'],
        Layout::class => ['layout']
    ];

    /**
     * @var SerializerContextBuilderInterface
     */
    private $decorated;

    public function __construct(SerializerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param string $group
     * @param bool $normalization
     * @param null|string $operation
     * @return array
     */
    private function getGroupNames(string $group, bool $normalization, ?string $operation): array
    {
        $groups = [$group, $group . ($normalization ? '_read' : '_write')];
        if ($operation) {
            $groups[] = "${group}_${operation}";
        }
        return $groups;
    }

    /**
     * @param $className
     * @param $matchClassName
     * @return bool
     */
    private function matchClass($className, $matchClassName): bool
    {
        return $className === $matchClassName || is_subclass_of($className, $matchClassName);
    }

    /**
     * @param string $subject
     * @param bool $normalization
     * @param string $operation
     * @return array
     */
    private function getGroups(string $subject, bool $normalization, ?string $operation): array
    {
        /** @var string[] $groups */
        $groups = [];
        foreach (self::CLASS_GROUP_MAPPING as $class=>$groupMapping) {
            if ($this->matchClass($subject, $class)) {
                foreach ($groupMapping as $group) {
                    $groups[] = $this->getGroupNames($group, $normalization, $operation);
                }
            }
        }
        return $groups;
    }

    /**
     * @param Request $request
     * @param bool $normalization
     * @param array|null $extractedAttributes
     * @return array
     * @throws \ApiPlatform\Core\Exception\RuntimeException
     */
    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null) : array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        if (isset($context['groups']) && \in_array('none', $context['groups'], true)) {
            return $context;
        }
        $subject = $request->attributes->get('_api_resource_class');
        $operation = $context['item_operation_name'] ?? null;
        $groups = $this->getGroups($subject, $normalization, $operation);

        if (\count($groups)) {
            if (!isset($context['groups'])) {
                $context['groups'] = ['default'];
            } else {
                $context['groups'][] = ['default'];
            }
            $context['groups'] = array_merge($context['groups'], ...$groups);
        }

        return $context;
    }
}
