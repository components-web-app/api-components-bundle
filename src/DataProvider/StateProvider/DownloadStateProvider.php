<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class DownloadStateProvider implements ProviderInterface
{
    public const string OPERATION_EXTRA_PROPERTY = 'silverback_download';

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private DownloadAction $downloadAction,
        private UploadableAttributeReader $uploadableAttributeReader,
        private UploadableFileManager $uploadableFileManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (!\is_object($data) || !$request instanceof Request || true !== ($operation->getExtraProperties()[self::OPERATION_EXTRA_PROPERTY] ?? null)) {
            return $data;
        }

        $response = ($this->downloadAction)($data, (string) $request->attributes->get('property'), $request, $this->uploadableAttributeReader, $this->uploadableFileManager);
        $request->attributes->set('data', $response);

        return $response;
    }
}
