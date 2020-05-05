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

## Output

Here is an example output

```json
{
    "@context": "/contexts/Collection",
    "@id": "/component/collections/46419a6ef41a492f95cc2b18ae697fbb",
    "@type": "Collection",
    "componentLocations": [],
    "componentGroups": [],
    "resourceIri": "/component/dummy_components",
    "perPage": 3,
    "collection": {
        "@id": "/component/dummy_components",
        "@type": "hydra:Collection",
        "hydra:member": [
            {
                "@id": "/component/dummy_components/0830edc89f454579b9007cab7f017989",
                "@type": "DummyComponent",
                "componentLocations": [],
                "uiComponent": null,
                "uiClassNames": null,
                "componentGroups": [],
                "_metadata": {
                    "persisted": true
                }
            },
            {
                "@id": "/component/dummy_components/09ff183046834f62a77c694b4bf8de1d",
                "@type": "DummyComponent",
                "componentLocations": [],
                "uiComponent": null,
                "uiClassNames": null,
                "componentGroups": [],
                "_metadata": {
                    "persisted": true
                }
            },
            {
                "@id": "/component/dummy_components/0b0201f92dca46e0981e02231eb3da9e",
                "@type": "DummyComponent",
                "componentLocations": [],
                "uiComponent": null,
                "uiClassNames": null,
                "componentGroups": [],
                "_metadata": {
                    "persisted": true
                }
            }
        ],
        "hydra:totalItems": 50,
        "hydra:view": {
            "@id": "/component/dummy_components?page=1",
            "@type": "hydra:PartialCollectionView",
            "hydra:first": "/component/dummy_components?page=1",
            "hydra:last": "/component/dummy_components?page=17",
            "hydra:next": "/component/dummy_components?page=2"
        }
    },
    "_metadata": {
        "persisted": true
    }
} 
```
