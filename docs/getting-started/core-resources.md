---
layout: default
parent: Getting Started
nav_order: 2
---
# Core Resources
{: .no_toc }

Here is a simple overview of all of the resources. We are creating documentation and will have more detail on the features in future. Feel free to look through our Behat test scenarios to check for undocumented features.

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Layout

Resource Endpoint: `/_/layouts`

##### Sample post request
{: .no_toc }

```json
{
  "reference": "primary",
  "uiComponent": "PrimaryLayout",
  "classNames": ["has-border"],
  "componentCollections": [ "/_/components_collections/abcd-1234" ]
}
```

This is a very simple component, the reference must be unique and the uiComponent is the name that your front-end application will recognise to display the layout and position the rendered page within it.

## Page

Resource endpoint: `/_/pages`

##### Sample post request
{: .no_toc }

```json
{
  "reference": "home",
  "title": "Home Page",
  "metaDescription": "I like search engines to display me...",
  "route": "/_/route/abcd-1234",
  "parentRoute": "/_/route/abcd-4567",
  "nested": false,
  "layout": "/_/layouts/abcd-1234",
  "componentCollections": [ "/_/components_collections/abcd-1234" ]
}
```

## ComponentCollection

Resource endpoint: `/_/component_collections`

##### Sample post request
{: .no_toc }

```json
{
  "reference": "header",
  "layouts": [ "/_/layouts/abcd-1234" ],
  "pages": [ "/_/pages/abcd-1234" ],
  "componentPositions": [ "/_/component_positions/abcd-1234" ],
  "allowedComponents": [ "/components/nav_bars" ]
}
```

You would usually have a component group within either page(s) or layout(s) and not both. However, it is possible to specify the collection to appear in as many of these resources as you like.

## ComponentPosition

Resource endpoint: `/_/component_positions`

You would normally create this resource at the same time as creating your component. It requires that you have already created a component as well.

##### Sample post request
{: .no_toc }

```json
{
  "componentCollection": "/_/component_collections/abcd-1234",
  "component": "/components/heroes/efgh-4567",
  "sortValue": 1
}
```

When a ComponentPosition resource is created with the same sort value as an existing ComponentPosition, the existing resource's sortValue (and all subsequent resource's sortValue) property will be increased to avoid duplicates.

## Route

Resource endpoint: `/_/routes`

##### Sample post request
{: .no_toc }

```json
{
  "path": "/contact",
  "name": "contact-page",
  "page": "/_/pages/abcd-1234",
  "redirect": "/_/routes/abcd-1234"
}
```

A front-end application loading the page path `/contact` will request the route resource `/_/routes//contact` to get the associated page.

The response will include `redirectPath` if a redirect exists and this will be to the deepest nested redirect. The response will also include the page of the redirected route, so the front-end can usually avoid another request.
