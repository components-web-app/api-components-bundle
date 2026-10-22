<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ResourceVersionConflictStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

final class ResourceVersionConflictStateProcessorTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_the_result_of_the_write_is_returned(): void
    {
        $component = new DummyPublishableComponent();
        $result = new \stdClass();

        self::assertSame($result, $this->processor(null, $result)->process($component, new Patch()));
    }

    public function test_a_versioned_resource_is_written_in_one_transaction(): void
    {
        $level = null;
        $decorated = $this->createStub(ProcessorInterface::class);
        $decorated->method('process')->willReturnCallback(function () use (&$level) {
            $level = $this->entityManager->getConnection()->getTransactionNestingLevel();

            return null;
        });

        (new ResourceVersionConflictStateProcessor($decorated, $this->registry, new PublishableAttributeReader($this->registry)))->process(new DummyPublishableComponent(), new Patch());

        self::assertSame(1, $level);
        self::assertSame(0, $this->entityManager->getConnection()->getTransactionNestingLevel());
    }

    public function test_a_resource_that_is_not_versioned_is_written_outside_a_transaction(): void
    {
        $level = null;
        $decorated = $this->createStub(ProcessorInterface::class);
        $decorated->method('process')->willReturnCallback(function () use (&$level) {
            $level = $this->entityManager->getConnection()->getTransactionNestingLevel();

            return null;
        });

        (new ResourceVersionConflictStateProcessor($decorated, $this->registry, new PublishableAttributeReader($this->registry)))->process(new ComponentGroup(), new Patch());

        self::assertSame(0, $level);
    }

    public function test_a_version_refused_at_commit_is_a_conflict_carrying_the_refusal(): void
    {
        $refusal = OptimisticLockException::lockFailed(new DummyPublishableComponent());

        try {
            $this->processor($refusal)->process(new DummyPublishableComponent(), new Patch());
            self::fail('No conflict was thrown.');
        } catch (ResourceVersionConflictException $exception) {
            self::assertSame($refusal, $exception->getPrevious());
        }
    }

    public function test_a_duplicate_draft_of_a_published_resource_is_a_conflict(): void
    {
        $draft = $this->scheduledDraft();
        $violation = self::uniqueViolation();

        try {
            $this->processor($violation)->process($draft, new Patch());
            self::fail('No conflict was thrown.');
        } catch (ResourceVersionConflictException $exception) {
            self::assertSame($violation, $exception->getPrevious());
        }
    }

    public function test_a_unique_violation_writing_a_draft_already_stored_is_not_a_conflict(): void
    {
        $draft = $this->scheduledDraft();
        $this->entityManager->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->processor(self::uniqueViolation())->process($draft, new Patch());
    }

    public function test_a_unique_violation_writing_a_new_resource_that_is_not_a_draft_is_not_a_conflict(): void
    {
        $component = new DummyPublishableComponent();
        $this->entityManager->persist($component);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->processor(self::uniqueViolation())->process($component, new Patch());
    }

    public function test_a_unique_violation_writing_a_resource_that_is_not_publishable_is_not_a_conflict(): void
    {
        $group = new ComponentGroup();
        $this->entityManager->persist($group);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->processor(self::uniqueViolation())->process($group, new Patch());
    }

    public function test_a_unique_violation_writing_something_that_is_not_an_object_is_not_a_conflict(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->processor(self::uniqueViolation())->process(null, new Patch());
    }

    private function scheduledDraft(): DummyPublishableComponent
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $this->entityManager->persist($published);
        $this->entityManager->flush();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);

        return $draft;
    }

    private static function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(new class('duplicate') extends \Exception implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        }, null);
    }

    private function processor(?\Throwable $failure, mixed $result = null): ResourceVersionConflictStateProcessor
    {
        $decorated = $this->createStub(ProcessorInterface::class);
        if (null !== $failure) {
            $decorated->method('process')->willThrowException($failure);
        } else {
            $decorated->method('process')->willReturn($result);
        }

        return new ResourceVersionConflictStateProcessor($decorated, $this->registry, new PublishableAttributeReader($this->registry));
    }
}
