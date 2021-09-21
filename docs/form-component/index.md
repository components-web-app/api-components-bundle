---
layout: default
nav_order: 4
---
# Form Component
{: .no_toc }

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Overview

The default bundle configuration is to enable this component as seen below.

```yaml
silverback_api_components:
  enabled_components:
    form: true #default
```

The Form resource allows you to define a Symfony Form type class and it will serialze an object `formView` representing the form. You can also send `POST` and `PATCH` requests to special endpoints created for the resource to validate fields of the form and submit the form.

The endpoint will have the following format

```
/component/forms/{id}/submit
```

## Usage

## Create the component.

Endpoint `/component/forms`. Example `POST`

```json
{
    "formType": "App\\Form\\FormType"
}
```

## Output

Here is an example of the data you will receive:

```json
{
    "@context": "/contexts/Form",
    "@id": "/component/forms/48b72a08-8fc5-11ea-9d59-003ee1c35971",
    "@type": "Form",
    "formType": "App\\Form\\FormType",
    "formView": {
        "vars": {
            "errors": [],
            "action": "",
            "api_request": true,
            "attr": {
                "novalidate": "novalidate"
            },
            "block_prefixes": [
                "form",
                "test",
                "_test"
            ],
            "disabled": false,
            "full_name": "test",
            "id": "test",
            "label_attr": [],
            "name": "test",
            "post_app_proxy": "/proxy",
            "realtime_validate_disabled": false,
            "required": true,
            "submitted": true,
            "unique_block_prefix": "_test",
            "valid": true,
            "value": {
                "name": "John Smith"
            }
        },
        "children": [
            {
                "vars": {
                    "errors": [],
                    "action": "",
                    "attr": [],
                    "block_prefixes": [
                        "form",
                        "text",
                        "_test_name"
                    ],
                    "disabled": false,
                    "full_name": "test[name]",
                    "id": "test_name",
                    "label_attr": [],
                    "name": "name",
                    "required": true,
                    "submitted": true,
                    "unique_block_prefix": "_test_name",
                    "valid": true,
                    "value": "John Smith"
                },
                "children": [],
                "rendered": false,
                "methodRendered": false,
                "form": {
                    "name": [],
                    "company": []
                }
            },
            {
                "vars": {
                    "errors": [],
                    "action": "",
                    "attr": [],
                    "block_prefixes": [
                        "form",
                        "text",
                        "_test_company"
                    ],
                    "disabled": false,
                    "full_name": "test[company]",
                    "id": "test_company",
                    "label_attr": [],
                    "name": "company",
                    "required": true,
                    "submitted": false,
                    "unique_block_prefix": "_test_company",
                    "valid": true,
                    "value": ""
                },
                "children": [],
                "rendered": false,
                "methodRendered": false,
                "form": {
                    "name": [],
                    "company": []
                }
            }
        ],
        "rendered": false,
        "methodRendered": false,
        "form": {
            "name": [],
            "company": []
        }
    },
    "componentLocations": [],
    "componentGroups": [],
    "modifiedAt": "2020-05-06T18:13:27+00:00",
    "createdAt": "2020-05-06T18:13:27+00:00",
    "_metadata": {
        "persisted": true
    }
} 
```

## Submitting form data

### PATCH / Validate fields

Endpoint: `/component/forms/{id}/submit`

Instead of duplicating validation in the front-end application and having to keep it sycnhronised in your API, you can easily validate a single field (or group of fields). Here is an example of what you could submit:

```json
{
  "name": "",
  "company": "company"
}
```

If the validation is successful, you will receive a `200` HTTP status code. Otherwise you will receive a `400` status code. In both instances you will receive the exact same structure of a serialized form as when you get a form resource. There will be keys on each item that is submitted and validation errors where applicable.

Example:
```json
{
    "@context": "/contexts/Form",
    "@id": "/component/forms/eb48bf02-8fc5-11ea-95f7-003ee1c35971",
    "@type": "Form",
    "formType": "App\\Form\\FormType",
    "formView": {
        "vars": {
            "errors": [],
            "action": "",
            "api_request": true,
            "attr": {
                "novalidate": "novalidate"
            },
            "block_prefixes": [
                "form",
                "test",
                "_test"
            ],
            "disabled": false,
            "full_name": "test",
            "id": "test",
            "label_attr": [],
            "name": "test",
            "post_app_proxy": "/proxy",
            "realtime_validate_disabled": false,
            "required": true,
            "submitted": true,
            "unique_block_prefix": "_test",
            "valid": false,
            "value": {
                "name": null,
                "company": "company"
            }
        },
        "children": [
            {
                "vars": {
                    "errors": [
                        "Please provide your name"
                    ],
                    "action": "",
                    "attr": [],
                    "block_prefixes": [
                        "form",
                        "text",
                        "_test_name"
                    ],
                    "disabled": false,
                    "full_name": "test[name]",
                    "id": "test_name",
                    "label_attr": [],
                    "name": "name",
                    "required": true,
                    "submitted": true,
                    "unique_block_prefix": "_test_name",
                    "valid": false,
                    "value": ""
                },
                "children": [],
                "rendered": false,
                "methodRendered": false,
                "form": {
                    "name": [],
                    "company": []
                }
            },
            {
                "vars": {
                    "errors": [],
                    "action": "",
                    "attr": [],
                    "block_prefixes": [
                        "form",
                        "text",
                        "_test_company"
                    ],
                    "disabled": false,
                    "full_name": "test[company]",
                    "id": "test_company",
                    "label_attr": [],
                    "name": "company",
                    "required": true,
                    "submitted": true,
                    "unique_block_prefix": "_test_company",
                    "valid": true,
                    "value": "company"
                },
                "children": [],
                "rendered": false,
                "methodRendered": false,
                "form": {
                    "name": [],
                    "company": []
                }
            }
        ],
        "rendered": false,
        "methodRendered": false,
        "form": {
            "name": [],
            "company": []
        }
    },
    "componentLocations": [],
    "componentGroups": [],
    "modifiedAt": "2020-05-06T18:18:00+00:00",
    "createdAt": "2020-05-06T18:18:00+00:00",
    "_metadata": {
        "persisted": true
    }
}
```

### POST (submitting the form)

Endpoint: `/component/forms/{id}/submit`

This is very similar to a validation request where you receive the form back and a HTTP status code of `400` for an invalid submission. For a successful submission the status code is `201` and by default you will still receive the form back.

#### Form Success Listeners

On a successful submission, an event is fired `Silverback\ApiComponentsBundle\Event\FormSuccessEvent`. You can hook into this event just as you can with any other event in Symfony.

You have 2 useful methods to use the form data: `FormSuccessEvent::getForm()` which returns the form resource and `FormSuccessEvent::getFormData()` which is a shortcut to `FormSuccessEvent::getForm()->formView->getForm()->getData()` and will return the submitted form data.

If you set `FormSuccessEvent->result`, then whatever you set will be serialized and returned to your API user.

#### Reusable EntityPersistFormListener

You can re-use a listener if you simply want to persist the data in your submitted form to the database.

Create your class, for example:

> **Using this listener will result in your object being serialised and returned to the API User upon successful submission by default. Set the 3rd parameter on the parent constructor to `false` to disable this.**

```php
<?php

declare(strict_types=1);

namespace App\EventListener\Form\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;

class NewEmailAddressListener extends EntityPersistFormListener
{
    public function __construct()
    {
        parent::__construct($supportedFormType = NewEmailAddressType::class, $supportedDataClass = AbstractUser::class, $returnFormDataOnSuccess = true);
    }
}

```

Register the service like this:

```php
use App\EventListener\Form\User\NewEmailAddressListener;
use  Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use  Silverback\ApiComponentsBundle\Event\FormSuccessEvent;

$services
    ->set(NewEmailAddressListener::class)
    ->parent(EntityPersistFormListener::class)
    ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);
```

or

```yaml
App\EventListener\Form\User\NewEmailAddressListener:
    parent: Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener
    tags:
        - { name: 'kernel.event_listener', event: 'Silverback\ApiComponentsBundle\Event\FormSuccessEvent' }
```
