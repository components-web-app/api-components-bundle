---
layout: default
nav_order: 5
---
# Collection Component

The following configuration can be used to explicitly disable or enable the collection resource. By default it is enabled.

```yaml
silverback_api_component:
  enabled_components:
    collection: true  # default
```

The Collection resource is designed to be a proxy which will output another resource's collection in it's `Collection::$collection` property. For many websites, you will want to output a list of another resource (for example a list of articles). This makes it much easier by reading the collection within the component and allows you to configure a default number of items to display per page and query string parameters that you would also be able to use in your front-end application to filter resources.

## Configure the total number of items to return

This parameter can be configured whether or not you have enabled `client_items_per_page` in API Platform. This is because you may want to list the first `x` resources returned on a given page. Perhaps on the home page, if you've ordered your collection by `createdAt`, you could list the most recent 3 blog articles.

> **IMPORTANT! Only use this if `client_items_per_page` is not enabled and your front-end application does not need to reload this collection without reloading this resource. Alternatively enable `client_items_per_page`, `maximum_items_per_page` and then set `defaultQueryParameters` on the Collection resource.**

## Automatic passing of querystring parameters to the collection

When you request a Collection resource, the querystring parameters will also be passed to the underlying collection. This is so the front-end application could load the collection in a default state. For example, you may want to have a link to a unique URL which pre-fills a search query.
