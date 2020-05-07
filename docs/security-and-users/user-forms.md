---
layout: default
title: User Forms
parent: Security &amp; Users
nav_order: 2
---
# User Forms

In order to perform some user functionality, certain fields need to be set that cannot be accessed via the API directly for security reasons. We would not an anonymous user (one who has not logged on) to access fields that a logged in user can.

To solve this we have pre-made Symfony forms that you can use in a [Form component](../components/form-component.md) to use in your front-end web application. Changes to the user, or new user entities will be updated/persisted into the database upon successful form submission.

If you need additional fields, you can extend these forms to fit your requirements.

#### Register form

Form Type: `Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType`

#### New email address form

Form Type: `Silverback\ApiComponentBundle\Form\Type\User\NewEmailAddressType`

#### Change password form

Form Type: `Silverback\ApiComponentBundle\Form\Type\User\ChangePasswordType`
