---
layout: default
parent: Security &amp; Users
nav_order: 1
---
# User Login

>__Because the login process serves a JWT Refresh token, this must be done by your front-end server-side application and authenticated using an API Token.__

## Login Form
You can create a [Form component](../components/form-component.md) which references a pre-configured Symfony Form for logins `Silverback\ApiComponentBundle\Form\Type\User\UserLoginType`. This adds a hidden field `_action` which is an absolute URL to the API's login endpoint for JWT authentication. It also has a some defaults set on the form that your front-end application can read:
```php
[
    'attr' => [
        'id' => 'login_form',
        'novalidate' => 'novalidate',
    ],
    'action' => '/login',
    'realtime_validate' => false,
    'api_request' => false,
];
```
The action is pre-set and will call your front end's `/login` endpoint instead of calling the API because `'api_request' => false`. We also let the front-end application know that this form should not put in real-time verification requests with `'realtime_validate' => false`. A couple of HTML attributes are defined for your convenience too.

You can create this component as you would create any other form component, but _it does require that you have not disabled the in-built form component._
```yaml
silverback_api_component:
    enabled_components:
        form: true # <-- this is the default value
```

> It is the application's responsibility to save the refresh token server-side and refresh the token when appropriate.

## Using the refresh token
Take a look at the [JWTRefreshTokenBundle documentation](https://github.com/markitosgv/JWTRefreshTokenBundle) for the refresh token bundle to ensure you have configured everything correctly to allow your front-end application to refresh the token. Also ensure you have setup the bundle's parameters as described in [Security & Users > Getting Started](index.md#Configure-security-and-firewalls)
