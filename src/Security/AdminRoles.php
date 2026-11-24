<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Security;

final class AdminRoles
{
    public const string CMS_ADMIN = 'ROLE_CMS_ADMIN';
    public const string USER_ADMIN = 'ROLE_USER_ADMIN';
    public const string CMS_ADMIN_SECURITY = "is_granted('ROLE_CMS_ADMIN')";
    public const string USER_ADMIN_SECURITY = "is_granted('ROLE_USER_ADMIN')";

    public const array HIERARCHY = [
        'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', self::USER_ADMIN, 'ROLE_ALLOWED_TO_SWITCH'],
        'ROLE_ADMIN' => [self::CMS_ADMIN],
        self::CMS_ADMIN => ['ROLE_USER'],
        self::USER_ADMIN => ['ROLE_USER'],
    ];

    private function __construct()
    {
    }
}
