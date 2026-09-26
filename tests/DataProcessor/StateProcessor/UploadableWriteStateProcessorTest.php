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

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\UploadableWriteStateProcessor;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;

class UploadableWriteStateProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation}>
     */
    public static function writes(): iterable
    {
        yield 'POST' => [new Post()];
        yield 'PUT' => [new Put()];
        yield 'PATCH' => [new Patch()];
    }

    #[DataProvider('writes')]
    public function test_files_are_persisted_before_the_write_and_their_metadata_stored_after_it(Operation $operation): void
    {
        $data = new DummyUploadable();
        $calls = [];
        $fileManager = $this->createMock(UploadableFileManager::class);
        $fileManager->expects(self::once())->method('persistFiles')->with($data)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'persist files';
        });
        $fileManager->expects(self::once())->method('storeFilesMetadata')->with($data)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'store metadata';
        });
        $fileManager->expects(self::never())->method('deleteFiles');

        $result = (new UploadableWriteStateProcessor($this->inner($calls), $this->reader(true), $fileManager))->process($data, $operation);

        self::assertSame('written', $result);
        self::assertSame(['persist files', 'write', 'store metadata'], $calls);
    }

    /**
     * @return iterable<string, array{mixed, Operation, bool}>
     */
    public static function untouched(): iterable
    {
        yield 'a read' => [new DummyUploadable(), new Get(), true];
        yield 'a delete, whose files the Doctrine deletion listener removes after the flush' => [new DummyUploadable(), new Delete(), true];
        yield 'a resource that is not uploadable' => [new DummyUploadable(), new Patch(), false];
        yield 'no object' => [null, new Patch(), true];
        yield 'an operation that is not HTTP' => [new DummyUploadable(), new Mutation(), true];
    }

    #[DataProvider('untouched')]
    public function test_no_file_is_touched_for(mixed $data, Operation $operation, bool $uploadable): void
    {
        $calls = [];
        $fileManager = $this->createMock(UploadableFileManager::class);
        $fileManager->expects(self::never())->method('persistFiles');
        $fileManager->expects(self::never())->method('storeFilesMetadata');
        $fileManager->expects(self::never())->method('deleteFiles');

        self::assertSame('written', (new UploadableWriteStateProcessor($this->inner($calls), $this->reader($uploadable), $fileManager))->process($data, $operation));
        self::assertSame(['write'], $calls);
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $data = new DummyUploadable();
        $operation = new Patch();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($data, $operation, ['id' => 1], ['a' => 'b']);

        (new UploadableWriteStateProcessor($inner, $this->reader(true), $this->createStub(UploadableFileManager::class)))->process($data, $operation, ['id' => 1], ['a' => 'b']);
    }

    /**
     * @param list<string> $calls
     */
    private function inner(array &$calls): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'write';

            return 'written';
        });

        return $inner;
    }

    private function reader(bool $configured): UploadableAttributeReaderInterface
    {
        $reader = $this->createStub(UploadableAttributeReaderInterface::class);
        $reader->method('isConfigured')->willReturn($configured);

        return $reader;
    }
}
