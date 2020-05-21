---
layout: default
nav_order: 3
---
# Core resources

### Final core resources

> All resources within the namespace `Silverback\ApiComponentsBundle\Entity` will have the prefix `/_/` (e.g. `/_/routes/{id}`).

- `Route` (docs coming soon)
- `Layout` (docs coming soon)
- `Page` (docs coming soon)
- `ComponentCollection` (docs coming soon)
- `ComponentPosition` (docs coming soon)

### Abstract core resources
- `AbstractPageData` (docs coming soon)
> All resources extending AbstractPageData are prefixed with `/page_data` (e.g. `/page_data/article_page/{id}`). If you configure your own route prefix on your resource as well this will come AFTER. E.g. `/page_data/my_custom_prefix`
