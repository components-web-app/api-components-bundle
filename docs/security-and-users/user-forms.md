---
layout: default
title: User Forms
parent: Security &amp; Users
nav_order: 2
---
# User Forms

In order to perform some user functionality, certain fields need to be set that cannot be accessed via the API directly for security reasons. We would not an anonymous user (one who has not logged on) to access fields that a logged in user can.

To solve this we have pre-made Symfony forms that you can use in a [Form component](../components/form-component.md) to use in your front-end web application.

## Register form
- Form Type: `Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType`
- Listener: `Silverback\ApiComponentBundle\EventListener\Form\User\UserRegisterListener`

## New email address form
- Form Type: `Silverback\ApiComponentBundle\Form\Type\User\NewEmailAddressType`
- Listener: `Silverback\ApiComponentBundle\EventListener\Form\User\NewEmailAddressListener`

## Change password form
- Form Type: `Silverback\ApiComponentBundle\Form\Type\User\ChangePasswordType`
- Listener: _(not yet created - may implement a general EntityPersister listener for use in forms)_
