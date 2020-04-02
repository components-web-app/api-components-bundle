<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action\User;

use Silverback\ApiComponentBundle\Action\AbstractAction;
use Silverback\ApiComponentBundle\Manager\User\PasswordManager;
use Silverback\ApiComponentBundle\Serializer\RequestFormatResolver;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractPasswordAction extends AbstractAction
{
    protected PasswordManager $passwordManager;

    public function __construct(
        SerializerInterface $serializer,
        RequestFormatResolver $requestFormatResolver,
        PasswordManager $passwordManager
    ) {
        parent::__construct($serializer, $requestFormatResolver);
        $this->passwordManager = $passwordManager;
    }
}
