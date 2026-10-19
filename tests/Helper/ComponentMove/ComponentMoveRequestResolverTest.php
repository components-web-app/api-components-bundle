<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentMove;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveRequestResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;

class ComponentMoveRequestResolverTest extends TestCase
{
    private ComponentPosition $position;
    private ComponentGroup $group;
    private PageDataWithComponent $pageData;

    protected function setUp(): void
    {
        $this->position = new ComponentPosition();
        $this->group = new ComponentGroup();
        $this->pageData = new PageDataWithComponent();
    }

    public function test_a_position_moved_to_a_group_resolves_both_with_the_anchors(): void
    {
        [$from, $to] = $this->resolver()->resolve(['componentPosition' => '/position'], ['componentGroup' => '/group', 'after' => '/after', 'before' => '/before']);

        self::assertSame('from', $from->path);
        self::assertSame($this->position, $from->componentPosition);
        self::assertNull($from->componentGroup);
        self::assertNull($from->pageData);
        self::assertSame('to', $to->path);
        self::assertSame($this->group, $to->componentGroup);
        self::assertSame('/after', $to->after);
        self::assertSame('/before', $to->before);
        self::assertNull($to->componentPosition);
        self::assertNull($to->pageData);
    }

    public function test_a_group_target_with_no_anchor_has_null_anchors(): void
    {
        [, $to] = $this->resolver()->resolve(['componentPosition' => '/position'], ['componentGroup' => '/group']);

        self::assertSame($this->group, $to->componentGroup);
        self::assertNull($to->after);
        self::assertNull($to->before);
    }

    public function test_a_slot_moved_to_a_slot_resolves_the_page_data_and_properties(): void
    {
        [$from, $to] = $this->resolver()->resolve(['pageData' => '/page_data', 'property' => 'component'], ['property' => 'publishableComponent', 'pageData' => '/page_data']);

        self::assertSame($this->pageData, $from->pageData);
        self::assertSame('component', $from->property);
        self::assertNull($from->componentPosition);
        self::assertSame($this->pageData, $to->pageData);
        self::assertSame('publishableComponent', $to->property);
        self::assertNull($to->componentGroup);
        self::assertSame('to.property', $to->propertyPath('property'));
    }

    /**
     * @return iterable<string, array{mixed, mixed, list<array{string, string}>}>
     */
    public static function refusedBodies(): iterable
    {
        $shapeFrom = ComponentMoveRequestResolver::FROM_SHAPE_MESSAGE;
        $shapeTo = ComponentMoveRequestResolver::TO_SHAPE_MESSAGE;
        $string = ComponentMoveRequestResolver::NOT_A_STRING_MESSAGE;

        yield 'no from or to' => [null, null, [['from', $shapeFrom], ['to', $shapeTo]]];
        yield 'a from that is a string' => ['/position', ['componentGroup' => '/group'], [['from', $shapeFrom]]];
        yield 'a from with an extra key' => [['componentPosition' => '/position', 'after' => '/after'], ['componentGroup' => '/group'], [['from', $shapeFrom]]];
        yield 'a from that is a group' => [['componentGroup' => '/group'], ['componentGroup' => '/group'], [['from', $shapeFrom]]];
        yield 'a from page data with no property' => [['pageData' => '/page_data'], ['componentGroup' => '/group'], [['from', $shapeFrom]]];
        yield 'an empty from' => [[], ['componentGroup' => '/group'], [['from', $shapeFrom]]];
        yield 'a to that is a position' => [['componentPosition' => '/position'], ['componentPosition' => '/position'], [['to', $shapeTo]]];
        yield 'a to slot with an anchor' => [['componentPosition' => '/position'], ['pageData' => '/page_data', 'property' => 'component', 'before' => '/before'], [['to', $shapeTo]]];
        yield 'a to with only anchors' => [['componentPosition' => '/position'], ['after' => '/after'], [['to', $shapeTo]]];
        yield 'a position IRI that is not a string' => [['componentPosition' => 5], ['componentGroup' => '/group'], [['from.componentPosition', $string]]];
        yield 'a position that does not exist' => [['componentPosition' => '/missing'], ['componentGroup' => '/group'], [['from.componentPosition', 'The IRI `/missing` is not a component position.']]];
        yield 'a position IRI that is not an IRI' => [['componentPosition' => 'nothing'], ['componentGroup' => '/group'], [['from.componentPosition', 'The IRI `nothing` is not a component position.']]];
        yield 'a position IRI of a group' => [['componentPosition' => '/group'], ['componentGroup' => '/group'], [['from.componentPosition', 'The IRI `/group` is not a component position.']]];
        yield 'a group IRI of a position' => [['componentPosition' => '/position'], ['componentGroup' => '/position'], [['to.componentGroup', 'The IRI `/position` is not a component group.']]];
        yield 'a page data IRI of a group' => [['pageData' => '/group', 'property' => 'component'], ['componentGroup' => '/group'], [['from.pageData', 'The IRI `/group` is not a page data resource.']]];
        yield 'an anchor that is not a string' => [['componentPosition' => '/position'], ['componentGroup' => '/group', 'after' => 1], [['to.after', $string]]];
        yield 'a before anchor that is not a string' => [['componentPosition' => '/position'], ['componentGroup' => '/group', 'before' => []], [['to.before', $string]]];
        yield 'a property that is not a string' => [['pageData' => '/page_data', 'property' => 1], ['componentGroup' => '/group'], [['from.property', $string]]];
        yield 'a property and page data that are both wrong' => [['componentPosition' => '/position'], ['pageData' => '/missing', 'property' => false], [['to.pageData', 'The IRI `/missing` is not a page data resource.'], ['to.property', $string]]];
        yield 'a wrong group and wrong anchors' => [['componentPosition' => '/missing'], ['componentGroup' => 3, 'after' => 1, 'before' => 2], [['from.componentPosition', 'The IRI `/missing` is not a component position.'], ['to.componentGroup', $string], ['to.after', $string], ['to.before', $string]]];
    }

    /**
     * @param list<array{string, string}> $expected
     */
    #[DataProvider('refusedBodies')]
    public function test_a_body_that_does_not_resolve_is_refused_with_every_violation(mixed $from, mixed $to, array $expected): void
    {
        try {
            $this->resolver()->resolve($from, $to);
            self::fail('The body was resolved.');
        } catch (ValidationException $exception) {
            $actual = [];
            foreach ($exception->getConstraintViolationList() as $violation) {
                $actual[] = [$violation->getPropertyPath(), (string) $violation->getMessage()];
            }
            self::assertSame($expected, $actual);
        }
    }

    public function test_a_violation_carries_the_invalid_value_and_parameters(): void
    {
        try {
            $this->resolver()->resolve(['componentPosition' => '/missing'], ['componentGroup' => '/group']);
            self::fail('The body was resolved.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            self::assertSame('/missing', $violation->getInvalidValue());
            self::assertSame(ComponentMoveRequestResolver::NOT_FOUND_MESSAGE, $violation->getMessageTemplate());
            self::assertSame(['{{ iri }}' => '/missing', '{{ type }}' => 'component position'], $violation->getParameters());
        }
    }

    private function resolver(): ComponentMoveRequestResolver
    {
        $resources = ['/position' => $this->position, '/group' => $this->group, '/page_data' => $this->pageData];
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturnCallback(static fn (string $iri): object => $resources[$iri] ?? ('/missing' === $iri ? throw new ItemNotFoundException('Not found') : throw new InvalidArgumentException('No route')));

        return new ComponentMoveRequestResolver($iriConverter);
    }
}
