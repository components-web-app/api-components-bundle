<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Metadata\CollectionOperationInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\User\CurrentUserMatcher;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
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

    public const ALREADY_CALLED = 'USER_NORMALIZER_ALREADY_CALLED';

    private const string SELF_ONLY_PROPERTY = 'adminPreferences';

    public function __construct(
        private readonly UserDataProcessor $userDataProcessor,
        private readonly RoleHierarchy $roleHierarchy,
        private readonly ResourceMetadataProvider $resourceMetadataProvider,
        private readonly MercureAuthorization $mercureAuthorization,
        private readonly CurrentUserMatcher $currentUserMatcher,
    ) {
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return \is_array($data) && !isset($context[self::ALREADY_CALLED]) && is_subclass_of($type, AbstractUser::class);
    }

    public function denormalize($data, $type, $format = null, array $context = []): AbstractUser
    {
        $context[self::ALREADY_CALLED] = true;

        $populated = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $oldObject = $populated instanceof AbstractUser ? clone $populated : null;

        if (null !== $oldObject && $this->isSelf($oldObject, $context)) {
            $context = $this->withSelfGroup($context);
        } else {
            unset($data[self::SELF_ONLY_PROPERTY]);
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

        $subscribeTopics = $this->mercureAuthorization->getSubscribeTopics();
        $metadata = $this->resourceMetadataProvider->findResourceMetadata($object);
        $metadata->setMercureSubscribeTopics($subscribeTopics);

        $self = $this->isSelf($object, $context);
        if ($self) {
            $context = $this->withSelfGroup($context);
        }

        $normalized = $this->normalizer->normalize($object, $format, $context);
        if (!\is_array($normalized)) {
            return $normalized;
        }
        if (\array_key_exists('roles', $normalized)) {
            $normalized['roles'] = array_values($this->roleHierarchy->getReachableRoleNames($object->getRoles()));
        }
        if ($self) {
            $normalized[self::SELF_ONLY_PROPERTY] = $object->getAdminPreferences();
        } else {
            unset($normalized[self::SELF_ONLY_PROPERTY]);
        }

        return $normalized;
    }

    private function isSelf(AbstractUser $user, array $context): bool
    {
        if (isset($context[MercureResourcePublisher::NORMALIZATION_CONTEXT_KEY])) {
            return false;
        }
        if (($context['root_operation'] ?? $context['operation'] ?? null) instanceof CollectionOperationInterface) {
            return false;
        }

        return $this->currentUserMatcher->isCurrentUser($user);
    }

    private function withSelfGroup(array $context): array
    {
        if (isset($context['groups']) && \is_array($context['groups'])) {
            $context['groups'][] = AbstractUser::SELF_GROUP;
        }

        return $context;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [AbstractUser::class => false];
    }
}
