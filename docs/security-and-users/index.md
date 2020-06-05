---
layout: default
nav_order: 3
has_children: true
---
# Security & Users

## Types of authentication
### JWT Tokens for Users

This bundle uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle) with some additions.

The API will refresh the token based on your bundle configuration. There is no refresh token returned to your final application. These are secrets which are stored internally. When an expired JWT token is used, the API will return a new JWT token in a `Set-Cookie` header.

## Getting started

### Configure security and firewalls
As described [here](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#getting-started) - generate the keys for JWTs. (You fill find the passphrase that has been generated in your environment variables by the flex recipe for LexikJWTAuthenticationBundle)
```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Configure your security/firewall. Below is a recommended configuration, **but please check it and ensure it meets your needs**:
```yaml
# /config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_ADMIN:       ROLE_USER
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    encoders:
        Silverback\ApiComponentsBundle\Entity\User\AbstractUser:
            algorithm: auto
    providers:
        user_provider:
            entity:
                class: Silverback\ApiComponentsBundle\Entity\User\AbstractUser
        jwt:
            lexik_jwt:
                class: Silverback\ApiComponentBundle\Entity\User\AbstractUser
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        login:
            pattern:  ^/login
            stateless: true
            anonymous: lazy
            provider: user_provider
            user_checker: Silverback\ApiComponentsBundle\Security\UserChecker
            json_login:
                check_path: /login
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
        main:
            pattern:   ^/
            stateless: true
            anonymous: lazy
            provider: jwt
            logout:
                path: /logout
                # Required for Symfony < 5.1
                # success_handler: silverback.security.logout_handler
            guard:
                authenticators:
                    - lexik_jwt_authentication.jwt_token_authenticator
            # https://symfony.com/doc/current/security/impersonating_user.html
            switch_user: true
    access_control:
        - { path: ^/token/refresh, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/password/(reset|update), roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST] }
        # The 2 options below prevents anonymous users from making changes to your API resources while allowing form submissions
        - { path: ^/component/forms/(.*)/submit, roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST, PATCH] }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY, methods: [POST, PUT, PATCH, DELETE] }
```

### JWT Authentication
By using the flex recipe, you will already have a pre-configured `App\Entity\User` entity in your project.

By default, although there is a column for the username and one for the email address in the database, these are kept synchronised by the `User` class. You can modify the class to suit your needs. Just be sure to extend the class `Silverback\ApiComponentBundle\Entity\User\AbstractUser`.

The repository automatically configured for your User entity will look up users by their email address or username properties.

If you do not use Flex, or you create a difference User class you must configure this in the bundle:
```yaml
silverback_api_component:
  user:
    class_name: App\Entity\User
```

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    set_cookies:
        api_component:
            lifetime: 604800 # 1 week
```

### Refresh token configuration

This documentation is incomplete. We may add the `set_cookies` configuration for `lexik_jwt_authentication` automatically based on the cookie name below. TBD.

```yaml
silverback_api_components:
    refresh_token:
        handler_id: silverback.api_component.refresh_token.storage.doctrine
        options:
            class: Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RefreshToken
        cookie_name: api_component
        ttl: 604800 # 1 week
```
