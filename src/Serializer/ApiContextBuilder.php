<?php

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponentItem;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Component\HttpFoundation\Request;

class ApiContextBuilder implements SerializerContextBuilderInterface
{
    private $decorated;

    public function __construct(SerializerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    private function getGroups(string $group, bool $normalization)
    {
        return [$group, $group . ($normalization ? '_read' : '_write')];
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
        $subject = $request->attributes->get('_api_resource_class');
        $groups = [];
        if (
            is_subclass_of($subject, AbstractComponent::class) ||
            is_subclass_of($subject, AbstractComponentItem::class)
        ) {
            $groups[] = $this->getGroups('component', $normalization);
        }
        if (is_subclass_of($subject, AbstractContent::class)) {
            $groups[] = $this->getGroups('content', $normalization);
        }
        if (is_subclass_of($subject, Layout::class)) {
            $groups[] = $this->getGroups('layout', $normalization);
        }

        if (!isset($context['groups'])) {
            $context['groups'] = [];
        }
        $context['groups'] = array_merge($context['groups'], ...$groups);
        return $context;
    }
}
