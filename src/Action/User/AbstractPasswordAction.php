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

namespace Silverback\ApiComponentsBundle\Action\User;

use Silverback\ApiComponentsBundle\Action\AbstractAction;
use Silverback\ApiComponentsBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentsBundle\Helper\User\PasswordManager;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractPasswordAction extends AbstractAction
{
    protected PasswordManager $passwordManager;

    public function __construct(SerializerInterface $serializer, SerializeFormatResolver $requestFormatResolver, ResponseFactory $responseFactory, PasswordManager $passwordManager)
    {
        parent::__construct($serializer, $requestFormatResolver, $responseFactory);
        $this->passwordManager = $passwordManager;
    }
}
