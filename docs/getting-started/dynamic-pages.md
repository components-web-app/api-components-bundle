---
layout: default
parent: Getting Started
nav_order: 2
---
# Dynamic Pages

### Abstract core resources
- `AbstractPageData` (docs coming soon)
> All resources extending AbstractPageData are prefixed with `/page_data` (e.g. `/page_data/article_page/{id}`). If you configure your own route prefix on your resource as well this will come AFTER. E.g. `/page_data/my_custom_prefix`

#### Notes from an issue on how we will handle dynamic pages - reference for writing docs.

This will work in a different way to initially planned. We will create a page data provider which you will be able to inject into DataTransformers. Using the data you can modify any object being returned from the database.

If you want to provide an object that DOES NOT EXIST in the database, use a custom data provider. You could detect a reserved key/string passed as the ID for a resource and return a resource that is dynamically configured.

Remember the web-app will have metadata which will specify that the resource is not persisted to the database as it will not exist in the Doctrine object manager.

When you have modified a resource using a DataTransformer, we will use the PageDataProvider to modify variables. This will in turn add metadata to the output so the web app knows to modify a different API resource for its PUT requests.

We may also want to implement a ComponentResourcePlaceholder resource. This could simply be a placeholder component that references a PageData class and method/property which contains another component. The output should simply output the component the is references in its place.

This should solve all the dynamic page data issues.
