---
layout: default
nav_order: 2
has_children: true
---
# Security & Users

## Types of authentication
### JWT Tokens for Users
This bundle uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle) and [JWTRefreshTokenBundle](https://github.com/markitosgv/JWTRefreshTokenBundle) to provide an authentication method for users.

>Because refresh tokens have the potential for a long lifetime, developers should ensure that strict storage requirements are in place to keep them from being leaked. For example, on web applications, refresh tokens should only leave the backend when being sent to the authorization server, and the backend should be secure. The client secret should be protected in a similar fashion. Mobile applications do not require a client secret, but they should still be sure to store refresh tokens somewhere only the client application can access.

_(Source: https://auth0.com/learn/refresh-tokens/)_

### API Tokens for Applications
We also use a simple API Token Authenticator `Silverback\ApiComponentBundle\Security\TokenAuthenticator` so that endpoints which expose sensitive data can be secured. Primarily this is so that JWT Refresh tokens are not passed directly to users.

## Getting started

### Configure security and firewalls
As described [here](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#getting-started) - generate the keys for JWTs. (You fill find the passphrase that has been generated in your environment variables by the flex recipe for LexikJWTAuthenticationBundle)
```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Configure `JWTRefreshTokenBundle`:
```yaml
# /config/packages/gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
    # 30 day
    ttl: 2592000
    user_identity_field: username
    firewall: main
    user_provider: 'security.user.provider.concrete.user_provider'
```
```yaml
# /config/routes/gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
    path:     /token/refresh
    defaults: { _controller: gesdinet.jwtrefreshtoken:refresh }
```
The above configurations are a bit different to those that will be added by Symfony Flex for the package. The `user_provider` must use the database for us to retain the user's roles. For our purposes, the route does not need the `/api` prefix.

Configure your security/firewall:
```yaml
# /config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_ADMIN:       ROLE_USER
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    encoders:
        Silverback\ApiComponentBundle\Entity\User\AbstractUser:
            algorithm: auto
    providers:
        user_provider:
            entity:
                class: Silverback\ApiComponentBundle\Entity\User\AbstractUser
        jwt:
            lexik_jwt:
                class: Silverback\ApiComponentBundle\Entity\User\AbstractUser
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        refresh:
            pattern: ^/token/refresh
            stateless: true
            anonymous: true
        login:
            pattern:  ^/login
            stateless: true
            # anonymous: true
            provider: user_provider
            user_checker: Silverback\ApiComponentBundle\Security\UserChecker
            guard:
                authenticators:
                    - Silverback\ApiComponentBundle\Security\TokenAuthenticator
            json_login:
                check_path: /login
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
        main:
            pattern:   ^/
            stateless: true
            anonymous: true
            provider: jwt
            guard:
                authenticators:
                    - lexik_jwt_authentication.jwt_token_authenticator
            # https://symfony.com/doc/current/security/impersonating_user.html
            switch_user: true
    access_control:
        - { path: ^/login, roles: ROLE_TOKEN_USER }
        - { path: ^/token/refresh, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/password/reset, roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST] }
        - { path: ^/component/forms/(.*)/submit, roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST, PATCH] }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY, methods: [POST, PUT, PATCH, DELETE] }
```

### Token Authentication
As part of the Symfony Flex recipe, an environment variable `API_SECRET_TOKEN` will be generated and by default used in the bundle configuration:
```yaml
silverback_api_component:
    security:
        tokens:
          - '%env(API_SECRET_TOKEN)%'
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
