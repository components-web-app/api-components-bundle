---
layout: default
title: Notification Emails
parent: Security &amp; Users
nav_order: 3
---
# Notification Emails
{: .no_toc }

> __This bundle uses [Symfony Mailer](https://symfony.com/doc/current/components/mailer.html) and the Twig [Inky extension](https://symfony.com/doc/current/mailer.html#inky-email-templating-language). Take a look at the Twig templates included in this bundle and override them as necessary using the methods outlined by [Symfony: How to Override any Part of a Bundle](https://symfony.com/doc/current/bundles/override.html)__

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Common variables
All user emails receive the user object as `user` and the configured website name (in the bundle configuration) as `website_name`. Emails where a redirect URL is generated, the template will receive the variable `redirect_url`

## Variables in subjects
You can use any twig variables in the subject lines that are also available within the email templates. For example, you can include the user's username in a subject heading such as {% raw %}`Hi {{ user.username }}, great to have you on board!`{% endraw %}

## Template
You can reuse the template in any emails your own application sends if you wish. It can be found at `@SilverbackApiComponent/emails/_template`

## Signature
If you would like to adjust the signature across all of the emails you can edit the file `@SilverbackApiComponent/emails/_signature.html.twig`

## Redirect URLs

The `redirect_url` variable is generated using either the configured `default_redirect_path` value in the bundle configuration, or the value in the `redirect_path_query` query parameter made during the request. The query parameter can be a relative path or an absolute URL. For relative paths, the application will initially try to read the `origin` header from the request, followed by the `referer` header and use the value to make the absolute URL. If neither can be found, or there is an error parsing the value, an exception will be thrown.

## Emails

### Password Reset

Template: `@SilverbackApiComponent/emails/user_password_reset.html.twig`

```yaml
silverback_api_component:
  user:
    password_reset:
      email:
        redirect_path_query: ~
        default_redirect_path: /reset-password/{{ username }}/{{ token }} # Required
        subject: 'Your password has been reset'
      repeat_ttl_seconds: 8600
      request_timeout_seconds: 3600
```

#### Forgot password procedure

Make a `GET` request to `/password/reset/request/my_username`. You will receive a `200` http status code on success (or if it is another request within the TTL time) or `404` if the user is not found. The API will send a password reset email. 
> *n.b. You can also set the `redirect_path_query` parameter in the bundle configuration to configure the front-end redirect path dynamically. E.g. `redirect_path_query: redirect_path` and then `/password/reset/request/my_username?redirect_pat=/front-end-page/{{ username }}/{{ token }}`.*

Once redirected back to your application you will need to send a `POST` request to `/password/update`. Here is an example JSON request:

```json
{
  "username": "username",
  "token": "abc123",
  "password": "mynewpassword"
}
```

You will receive a `200` status code on a successful password change or `404` on not found. If the password does not pass validation you will receive a `400` response and here is an example JSON response:

```json
 {
    "@context": "/contexts/ConstraintViolationList",
    "@type": "ConstraintViolationList",
    "hydra:title": "An error occurred",
    "hydra:description": "plainPassword: Your password must be more than 6 characters long.",
    "violations": [
        {
            "propertyPath": "plainPassword",
            "message": "Your password must be more than 6 characters long."
        }
    ]
} 
```


### Email Address Verification

Template: `@SilverbackApiComponent/emails/user_change_email_confirmation.html.twig`

```yaml
silverback_api_component:
  user:
    email_verification:
      enabled: true
      email:
        redirect_path_query: ~
        default_redirect_path: /verify-new-email/{{ username }}/{{ token }} # Required
        subject:       'Please verify your email'
      default_value:    ~ # Required
      verify_on_change:   ~ # Required
      verify_on_register:  ~ # Required
      deny_unverified_login: ~ # Required
```

Your front-end should then perform a `GET` request to `/email_address/verify/{username}/{emailAddress}/{token}`. You will receive a `200` status on success, `404` if the combination is not found or `401` if the email change is aborted because another user now exists, so you are no longer authorised to make the change.

### Welcome
Template: `@SilverbackApiComponent/emails/user_welcome.html.twig`

{% raw %}
```yaml
silverback_api_component:
  user:
    emails:
      welcome:
        enabled: true
        subject: 'Welcome to {{ website_name }}'
```
{% endraw %}

### User Enabled
Template: `@SilverbackApiComponent/emails/user_enabled.html.twig`
```yaml
silverback_api_component:
  user:
    emails:
      user_enabled:
        enabled: true
        subject: 'Your account has been enabled'
```

### Username Changed
Template: `@SilverbackApiComponent/emails/user_username_changed.html.twig`
```yaml
silverback_api_component:
  user:
    emails:
      username_changed:
        enabled: true
        subject: 'Your username has been updated'
```

### Password Changed
Template: `@SilverbackApiComponent/emails/user_password_changed.html.twig`
```yaml
silverback_api_component:
  user:
    emails:
      password_changed:
        enabled: true
        subject: 'Your password has been changed'
```
