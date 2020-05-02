---
layout: default
nav_order: 0
---
# Bundle configuration

You can configure the name of the website the API is for which is used in a number of places, such as the default email signatures, and the API Swagger documentation. You can also adjust the default prefix applied to tables in your database. This is to prevent any naming conflicts.

## Website name (required)

This is used for a number of default features, including email signatures and adding a default title to your swagger documentation (if you remove the title configuration from the API Platform recipe)

```yaml
silverback_api_component:
    website_name: ~ # Required
```

## Table prefix

To prevent table name conflicts, we automatically prefix `_acb_` to the database tables that API Component Bundle manages. You can customise this using this configuration.

```yaml
silverback_api_component:
    table_prefix: _acb_
```

## Metadata key

Resources handled by API Components Bundle will include metadata. We inject this into a variable in your output which is `_metadata` by default. You can customise this in your configuration.

```yaml
silverback_api_component:
    metadata_key: _metadata
```
