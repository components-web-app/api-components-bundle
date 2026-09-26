<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DownloadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UploadStateProvider;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadAndDownloadStateProviderTest extends TestCase
{
    public function test_an_upload_operation_hands_the_read_resource_to_the_upload_action(): void
    {
        $read = new DummyUploadable();
        $uploaded = new DummyUploadable();
        $request = new Request();
        $fileManager = $this->createStub(UploadableFileManager::class);
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $action = $this->createMock(UploadAction::class);
        $action->expects(self::once())->method('__invoke')->with($read, $request, $fileManager, $statusChecker)->willReturn($uploaded);

        $provider = new UploadStateProvider($this->inner($read), $action, $fileManager, $statusChecker);

        self::assertSame($uploaded, $provider->provide($this->upload(), [], ['request' => $request]));
    }

    public function test_an_upload_operation_with_nothing_read_creates_the_resource_in_the_upload_action(): void
    {
        $request = new Request();
        $created = new DummyUploadable();
        $action = $this->createMock(UploadAction::class);
        $action->expects(self::once())->method('__invoke')->with(null, $request)->willReturn($created);

        $provider = new UploadStateProvider($this->inner(null), $action, $this->createStub(UploadableFileManager::class), $this->createStub(PublishableStatusChecker::class));

        self::assertSame($created, $provider->provide($this->upload(), [], ['request' => $request]));
    }

    public function test_other_operations_and_calls_without_a_request_are_not_uploads(): void
    {
        $read = new DummyUploadable();
        $action = $this->createMock(UploadAction::class);
        $action->expects(self::never())->method('__invoke');
        $provider = new UploadStateProvider($this->inner($read), $action, $this->createStub(UploadableFileManager::class), $this->createStub(PublishableStatusChecker::class));

        self::assertSame($read, $provider->provide(new Post(), [], ['request' => new Request()]));
        self::assertSame($read, $provider->provide(new Post(extraProperties: [UploadStateProvider::OPERATION_EXTRA_PROPERTY => 'yes']), [], ['request' => new Request()]));
        self::assertSame($read, $provider->provide($this->upload()));
    }

    public function test_a_download_operation_returns_the_file_response_and_sets_it_as_the_request_data(): void
    {
        $read = new DummyUploadable();
        $response = new Response('file');
        $request = new Request();
        $request->attributes->set('property', 'file');
        $reader = new UploadableAttributeReader($this->createStub(ManagerRegistry::class), false);
        $fileManager = $this->createStub(UploadableFileManager::class);
        $action = $this->createMock(DownloadAction::class);
        $action->expects(self::once())->method('__invoke')->with($read, 'file', $request, $reader, $fileManager)->willReturn($response);

        $provider = new DownloadStateProvider($this->inner($read), $action, $reader, $fileManager);

        self::assertSame($response, $provider->provide($this->download(), [], ['request' => $request]));
        self::assertSame($response, $request->attributes->get('data'));
    }

    public function test_other_operations_nothing_read_and_calls_without_a_request_are_not_downloads(): void
    {
        $read = new DummyUploadable();
        $action = $this->createMock(DownloadAction::class);
        $action->expects(self::never())->method('__invoke');
        $reader = new UploadableAttributeReader($this->createStub(ManagerRegistry::class), false);
        $request = new Request();

        self::assertSame($read, (new DownloadStateProvider($this->inner($read), $action, $reader, $this->createStub(UploadableFileManager::class)))->provide(new Get(), [], ['request' => $request]));
        self::assertSame($read, (new DownloadStateProvider($this->inner($read), $action, $reader, $this->createStub(UploadableFileManager::class)))->provide($this->download()));
        self::assertNull((new DownloadStateProvider($this->inner(null), $action, $reader, $this->createStub(UploadableFileManager::class)))->provide($this->download(), [], ['request' => $request]));
        self::assertFalse($request->attributes->has('data'));
    }

    private function upload(): Post
    {
        return new Post(extraProperties: [UploadStateProvider::OPERATION_EXTRA_PROPERTY => true]);
    }

    private function download(): Get
    {
        return new Get(extraProperties: [DownloadStateProvider::OPERATION_EXTRA_PROPERTY => true]);
    }

    private function inner(?object $data): ProviderInterface
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return $inner;
    }
}
