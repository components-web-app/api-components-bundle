<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Form;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class EntityPersistFormListenerTest extends TestCase
{
    public function test_a_user_cannot_be_compared_with_its_original_data_without_an_orm_entity_manager(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('flush');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The manager for %s must be a Doctrine ORM entity manager to compare the user with its original data', User::class));
        $this->createListener(User::class, $manager)($this->createEvent(new User('bob', 'bob@example.com')));
    }

    public function test_data_other_than_a_user_is_persisted_by_any_object_manager(): void
    {
        $data = new \ArrayObject();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('persist')->with($data);
        $manager->expects(self::once())->method('flush');

        $event = $this->createEvent($data);
        $this->createListener(\ArrayObject::class, $manager)($event);

        self::assertSame($data, $event->result);
    }

    public function test_the_normalizer_must_also_denormalize(): void
    {
        $listener = new class('form_type', User::class) extends EntityPersistFormListener {
        };

        $this->expectException(InvalidArgumentException::class);
        $listener->init(
            $this->createStub(ManagerRegistry::class),
            new TimestampedAttributeReader($this->createStub(ManagerRegistry::class)),
            $this->createStub(TimestampedDataPersister::class),
            $this->createStub(UserEventListener::class),
            $this->createStub(NormalizerInterface::class),
            $this->createStub(UserDataProcessor::class),
        );
    }

    private function createListener(string $dataClass, ObjectManager $manager): EntityPersistFormListener
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $listener = new class('form_type', $dataClass) extends EntityPersistFormListener {
        };
        $listener->init(
            $registry,
            new TimestampedAttributeReader($registry),
            $this->createStub(TimestampedDataPersister::class),
            $this->createStub(UserEventListener::class),
            new Serializer([], [new JsonEncoder()]),
            $this->createStub(UserDataProcessor::class),
        );

        return $listener;
    }

    private function createEvent(object $data): FormSuccessEvent
    {
        $form = new Form();
        $form->formType = 'form_type';
        $form->formView = new FormView(Forms::createFormFactory()->create(FormType::class, $data, ['data_class' => $data::class]));

        return new FormSuccessEvent($form);
    }
}
