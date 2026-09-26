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
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class UploadStateProvider implements ProviderInterface
{
    public const string OPERATION_EXTRA_PROPERTY = 'silverback_upload';

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private UploadAction $uploadAction,
        private UploadableFileManager $uploadableFileManager,
        private PublishableStatusChecker $publishableStatusChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (!$request instanceof Request || true !== ($operation->getExtraProperties()[self::OPERATION_EXTRA_PROPERTY] ?? null)) {
            return $data;
        }

        return ($this->uploadAction)(\is_object($data) ? $data : null, $request, $this->uploadableFileManager, $this->publishableStatusChecker);
    }
}
