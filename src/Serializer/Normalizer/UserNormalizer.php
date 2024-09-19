<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserNormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    // public so that EntityPersistFormListener can deserialize an old user for comparison and skip this normalizer from processing changes
    public const ALREADY_CALLED = 'USER_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly UserDataProcessor $userDataProcessor,
        private readonly RoleHierarchy $roleHierarchy,
        private readonly ResourceMetadataProvider $resourceMetadataProvider,
        private readonly MercureAuthorization $mercureAuthorization,
    ) {
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && is_subclass_of($type, AbstractUser::class);
    }

    public function denormalize($data, $type, $format = null, array $context = []): AbstractUser
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var AbstractUser $oldObject */
        $oldObject = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;

        if ($oldObject) {
            $oldObject = clone $oldObject;
        }

        /** @var AbstractUser $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        $this->userDataProcessor->processChanges($object, $oldObject);

        return $object;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof AbstractUser;
    }

    /**
     * @param AbstractUser $object
     * @param mixed|null   $format
     */
    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        $rolesAsEntities = $object->getRoles();
        $object->setRoles($this->roleHierarchy->getReachableRoleNames($rolesAsEntities));

        $subscribeTopics = $this->mercureAuthorization->getSubscribeTopics();
        $metadata = $this->resourceMetadataProvider->findResourceMetadata($object);
        $metadata->setMercureSubscribeTopics($subscribeTopics);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [AbstractUser::class => false];
    }
}
