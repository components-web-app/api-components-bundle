<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class UploadableWriteStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private UploadableAttributeReaderInterface $uploadableAttributeReader,
        private UploadableFileManager $uploadableFileManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (
            !\is_object($data)
            || !$operation instanceof HttpOperation
            || \in_array($operation->getMethod(), [HttpOperation::METHOD_GET, HttpOperation::METHOD_DELETE], true)
            || !$this->uploadableAttributeReader->isConfigured($data)
        ) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $this->uploadableFileManager->persistFiles($data);
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);
        $this->uploadableFileManager->storeFilesMetadata($data);

        return $result;
    }
}
