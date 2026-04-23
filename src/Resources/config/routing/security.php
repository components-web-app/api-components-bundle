<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator;

use Silverback\ApiComponentsBundle\Action\User\EmailAddressConfirmAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyNewEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\VerifyEmailAddressAction;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->add('api_components_login_check', '/login');

    $routes
        ->add('api_components_logout', '/logout');

    $routes
        ->add('api_components_password_reset_request', '/password/reset/request/{username}')
        ->methods(['GET'])
        ->controller(PasswordRequestAction::class);

    $routes
        ->add('api_components_confirm_email', '/confirm-email/{username}/{emailAddress}/{token}')
        ->methods(['GET'])
        ->controller(EmailAddressConfirmAction::class);

    $routes
        ->add('api_components_verify_email', '/verify-email/{username}/{token}')
        ->methods(['GET'])
        ->controller(VerifyEmailAddressAction::class);

    $routes
        ->add('api_components_resend_email_verification', '/verify-email/{username}/{token}')
        ->methods(['GET'])
        ->controller(ResendVerifyEmailAddressAction::class);

    $routes
        ->add('api_components_resend_new_email_verification', '/resend-verify-new-email/{username}')
        ->methods(['GET'])
        ->controller(ResendVerifyNewEmailAddressAction::class);
};
