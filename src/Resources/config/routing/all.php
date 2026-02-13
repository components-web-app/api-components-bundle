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

namespace Symfony\Component\Routing\Loader\Configurator;

use Silverback\ApiComponentsBundle\Action\User\EmailAddressConfirmAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyNewEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\VerifyEmailAddressAction;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('api_components_login_check', new Route('/login'));
$routes->add('api_components_logout', new Route('/logout'));
$routes->add('api_components_password_reset_request', new Route('/password/reset/request/{username}', [
    [
        '_controller' => PasswordRequestAction::class,
    ],
]));

$confirmEmailRoute = new Route('/confirm-email/{username}/{emailAddress}/{token}', [
    [
        '_controller' => EmailAddressConfirmAction::class,
    ],
]);
$confirmEmailRoute->setMethods(['GET']);
$routes->add('api_components_confirm_email', $confirmEmailRoute);

$verifyEmailRoute = new Route('/verify-email/{username}/{token}', [
    [
        '_controller' => VerifyEmailAddressAction::class,
    ],
]);
$confirmEmailRoute->setMethods(['GET']);
$routes->add('api_components_verify_email', $verifyEmailRoute);

$resendVerificationRoute = new Route('/verify-email/{username}/{token}', [
    [
        '_controller' => ResendVerifyEmailAddressAction::class,
    ],
]);
$confirmEmailRoute->setMethods(['GET']);
$routes->add('api_components_resend_email_verification', $resendVerificationRoute);

$resendNewEmailRoute = new Route('/resend-verify-new-email/{username}', [
    [
        '_controller' => ResendVerifyNewEmailAddressAction::class,
    ],
]);
$confirmEmailRoute->setMethods(['GET']);
$routes->add('api_components_resend_new_email_verification', $resendNewEmailRoute);

return $routes;
