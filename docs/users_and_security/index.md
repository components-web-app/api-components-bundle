# Users and security

We have built in user and security features to get you up and running quickly with industry standards for progressive web apps. It includes JWT authentication for users and roles as well as additional API Token authentication for endpoints which expose data or functionality that should only be available to the front-end application's server-side functionality. For example, you should NEVER expose a JWT refresh token to the end user.

We have included default login and register forms to use 'out of the box' and much more.

## Getting started
As described [here](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#getting-started) - generate the SSH keys for JWTs. (Use the passphrase that has been generated in your .env file - in production you can generate keys using /bin/rand_string.sh which will be located in the sample project which includes the API and front-end)
```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Configure your security/firewall. There is a configurable token so that requests to the API where a refresh token is returned can only be made from anotehr server. This is to protect the refresh key from being passed directly to a user. You should save the refresh key in your server-side session data and handle refreshing the JWT token that way.
```yaml
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
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
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
            guard:
                authenticators:
                    - lexik_jwt_authentication.jwt_token_authenticator
            # https://symfony.com/doc/current/security/impersonating_user.html
            switch_user: true
    access_control:
        - { path: ^/login, roles: ROLE_TOKEN_USER }
        - { path: ^/password/reset, roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST] }
        - { path: ^/component/forms/(.*)/submit, roles: IS_AUTHENTICATED_ANONYMOUSLY, methods: [POST, PATCH] }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY, methods: [POST, PUT, PATCH, DELETE] }
```

More docs coming soon.

- [Emails](./emails.md)
