---
layout: default
parent: Getting Started
nav_order: 1
---
# Step-by-step guide
{: .no_toc }

This will walk you through the process of creating your first page. In this example we will create the most basic page with just a single component.

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Create a Layout

Every page requires a Layout to be defined. This specifies which UI component will be used as the layout, usually the same for a number of pages. For example a layout may include a logo, a navigation bar and a footer.

In this example we will assume the layout will just include a logo as defined by the front-end application. Because we are choosing that that logo should not be updated by the website user, this will not need to have any data associated with it in the API and should be hosted by the front-end application.

So let's send a JSON POST request to the API endpoint `/_/layouts`:
```json
{
  "reference": "primary",
  "uiComponent": "PrimaryLayout"
}
```

You should get a 201 HTTP response with the new resource
```json
{
  "@context": "/contexts/Layout",
  "@id": "/_/layouts/41c57f7a-9f69-11ea-8188-acde48001122",
  "@type": "Layout",
  "reference": "primary",
  "pages": [],
  "createdAt": "2020-05-26T15:55:01+00:00",
  "modifiedAt": "2020-05-26T15:55:01+00:00",
  "uiComponent": "PrimaryLayout",
  "componentCollections": [],
  "_metadata": {
    "persisted": true
  }
}
```

## Create a Page

The page will define a UI component which will display `ComponentCollection` resources wherever you have configured it to do so in your front-end application. For example, the page could be a 2-column layout and there would be a `ComponentCollection` for each column. It must also define which layout it uses and a reference.

Let's create the page. Send a JSON POST request to the endpoint `/_/pages`:
```json
{
  "layout": "/_/layouts/41c57f7a-9f69-11ea-8188-acde48001122",
  "reference": "example-page",
  "uiComponent": "ExamplePageTemplateComponent",
  "title": "My Demo Page",
  "metaDescription": "Once upon a time I was learning how to create my resources for an API Component Bundle project."
}
```

You should get a 201 HTTP response with the new resource

```json
{
  "@context": "/contexts/Page",
  "@id": "/_/pages/56fd3fe0-9f69-11ea-9d75-acde48001122",
  "@type": "Page",
  "layout": "/_/layouts/41c57f7a-9f69-11ea-8188-acde48001122",
  "reference": "example-page",
  "createdAt": "2020-05-26T15:55:36+00:00",
  "modifiedAt": "2020-05-26T15:55:36+00:00",
  "uiComponent": "ExamplePageTemplateComponent",
  "componentCollections": [],
  "nested": true,
  "title": "My Demo Page",
  "metaDescription": "Once upon a time I was learning how to create my resources for an API Component Bundle project.",
  "_metadata": {
    "persisted": true
  }
}
```

## Create a ComponentCollection

The UI component in the front-end application will be looking for a `ComponentCollection` resource with a given reference so that it knows which collection of components to display where. In this example we will assume the UI component will only be expecting 1 ComponentCollection resource with the reference `main_body`.

Let's create this resource by sending a POST request to the endpoint `/_/component_collections`

```json
{
  "reference": "main_body",
  "pages": [
    "/_/pages/56fd3fe0-9f69-11ea-9d75-acde48001122"
  ]
}
```

You should get an HTTP 201 response with the resource

```json
{
  "@context": "/contexts/ComponentCollection",
  "@id": "/_/component_collections/6866c422-9f69-11ea-a2d3-acde48001122",
  "@type": "ComponentCollection",
  "reference": "main_body",
  "layouts": [],
  "pages": [],
  "components": [],
  "componentPositions": [],
  "createdAt": "2020-05-26T15:56:05+00:00",
  "modifiedAt": "2020-05-26T15:56:05+00:00",
  "_metadata": {
    "persisted": true
  }
}
```

## Create your Component

This bundle provides you with a simple way to create any component you could want on your website with its associated data. In this example, we will create a simple component which is designed to store and display HTML content. In your front-end you would implement a WYSIWYG editor such as Quill.

### Define the Component resource

Let's create our HtmlComponent class/entity. This is done just as you would if you were creating any API resource for API Platform and Doctrine except you will extend `AbstractComponent`.

Create the entity `/Entity/HtmlComponent`:

```php
<?php

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class HtmlComponent extends AbstractComponent
{
    /**
     * @ORM\Column(nullable=false)
     * @Assert\NotBlank()
     */
    public string $html;
}
```

...and just like that, you will have an endpoint to be able to create one of these components! We will not dive into detail with the annotations here. It is all very well documented in API Platform and Symfony.

You will have made all your components in the API before your front-end application needs to use them.

> **Remember to take a look at some very helpful [annotations and traits](/component-annotations/) you can use in your components to support more advanced features.**

### Add the component to your page

Let's create this component resource now. You could add the component and then add the `ComponentPosition` resource to place it within the ComponentCollection. However in the example below we will add the resource and position it in the same request.

You will notice all components you create are prefixed with `/component`.

Send your JSON request to the endpoint `/component/html_components`:

```json
{
  "html": "<p>Hello Covid World</p>",
  "componentPositions": [
    {
      "componentCollection": "/_/component_collections/6866c422-9f69-11ea-a2d3-acde48001122"
    }
  ],
  "uiComponent": "HtmlComponent"
}
```

You will receive a 201 HTTP response with the resource

```json
{
  "@context": "/contexts/HtmlComponent",
  "@id": "/component/html_components/27d0eb9c-9f71-11ea-af5a-acde48001122",
  "@type": "HtmlComponent",
  "html": "<p>Hello Covid World</p>",
  "uiComponent": "HtmlComponent",
  "componentCollections": [],
  "_metadata": {
    "persisted": true
  }
}
```

### 🎉 :star: Well done! you have just created your first web page in the API! :star: 🎉
{: .no_toc }
