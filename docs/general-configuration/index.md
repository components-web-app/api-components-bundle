---
layout: default
nav_order: 0
---
# General configuration

You can configure the name of the website the API is for which is used in a number of places, such as the default email signatures, and the API Swagger documentation. You can also adjust the default prefix applied to tables in your database. This is to prevent any naming conflicts.

```yaml
silverback_api_component:
    website_name:         ~ # Required
    table_prefix:         _acb_
```
