# Changelog

## Unreleased

### Service ID stabilisation (#86)

All services that previously used their PHP class name as the DI service ID have been renamed to stable `silverback.api_components.*` string identifiers. Class-name aliases are registered for every renamed service so existing code that resolves services by class name (autowiring, `getDefinition()` → now `findDefinition()`, direct `get()` calls) continues to work without change.

**Categories and selected public IDs introduced:**

| Category | Example stable IDs |
|---|---|
| `api_platform` | `silverback.api_components.api_platform.iri_converter`, `silverback.api_components.api_platform.property_metadata_factory.component`, `silverback.api_components.api_platform.metadata.resource.routing_prefix_factory`, `silverback.api_components.api_platform.metadata.resource.routable_factory`, `silverback.api_components.api_platform.metadata.resource.uploadable_factory`, `silverback.api_components.api_platform.metadata.resource.user_factory`, `silverback.api_components.api_platform.uuid_uri_variable_transformer`, `silverback.api_components.api_platform.state_provider.route`, `silverback.api_components.api_platform.state_provider.route_children`, `silverback.api_components.api_platform.state_provider.component_group`, `silverback.api_components.api_platform.state_provider.form`, `silverback.api_components.api_platform.state_provider.user`, `silverback.api_components.api_platform.state_provider.resource_manifest`, `silverback.api_components.api_platform.state_provider.page_data_metadata` |
| `serializer` | `silverback.api_components.serializer.normalizer.*` (one per normalizer class), `silverback.api_components.serializer.context_builder.cwa_resource`, `.component_position`, `.publishable`, `.timestamped`, `.uploadable`, `.user`, `silverback.api_components.serializer.mapping_loader.*`, `silverback.api_components.serializer.format_resolver`, `silverback.api_components.serializer.resource_metadata_provider` |
| `doctrine` | `silverback.api_components.doctrine.orm.extension.publishable`, `.route`, `.routable`, `silverback.api_components.doctrine.event_listener.publishable`, `.timestamped`, `.uploadable`, `.table_prefix`, `.sqlite_foreign_key_enabler`, `silverback.api_components.event_listener.doctrine.propagate_updates_listener` |
| `security` | `silverback.api_components.security.voter.route`, `.routable`, `.resource_manifest`, `.site_config_parameter`, `silverback.api_components.security.user_checker` |
| `event_listener` | `silverback.api_components.event_listener.api.collection`, `.form`, `.publishable`, `.uploadable`, `.user`, `silverback.api_components.event_listener.security.deny_access`, `silverback.api_components.event_listener.mailer.message`, `silverback.api_components.event_listener.form.*`, `silverback.api_components.event_listener.resource_changed`, `silverback.api_components.event_listener.imagine` |
| `helper` | `silverback.api_components.helper.publishable.status_checker`, `silverback.api_components.helper.uploadable.file_manager`, `silverback.api_components.helper.uploadable.file_info_cache_manager`, `silverback.api_components.helper.form.form_cache_purger`, `.form_submit`, `silverback.api_components.helper.referer_url_resolver`, `silverback.api_components.helper.user.email_address_manager`, `.mailer`, `.data_processor` |
| `factory` | `silverback.api_components.factory.uploadable.media_object`, `silverback.api_components.factory.form.form_view`, `silverback.api_components.factory.user`, `silverback.api_components.factory.user.mailer.*` |
| `repository` | `silverback.api_components.repository.file_info`, `.layout` |
| `command` | `silverback.api_components.command.form_cache_purge`, `.user_create`, `.clean_orphaned` |
| `validator` | `silverback.api_components.validator.component_position`, `.form_type_class`, `.new_email_address`, `.resource_iri`, `.user_password`, `.publishable`, `.timestamped`, `.mapping_loader.timestamped` |
| `mercure` | `silverback.api_components.mercure.authorization`, `.iri_converter`, `.publishable_aware_hub` |
| `form` | `silverback.api_components.form.change_password_type`, `.new_email_address_type`, `.password_update_type`, `.user_login_type`, `.user_register_type` |
| `fixture` | `silverback.api_components.fixture.cwa_fixture_builder` |
| `attribute_reader` | `silverback.api_components.attribute_reader.publishable`, `.timestamped`, `.uploadable` |
| `flysystem` | `silverback.api_components.flysystem.filesystem_factory`, `.filesystem_provider` |
| `action` | `silverback.api_components.action.uploadable.upload`, `.download`, `silverback.api_components.action.user.*` |
| `utility` | `silverback.api_components.utility.api_resource_route_finder` |

Previously stable string IDs (already existed before this change) are unchanged: `silverback.security.jwt_manager`, `silverback.security.jwt_event_listener`, `silverback.security.jwt_clear_token_listener`, `silverback.security.logout_listener`, `silverback.api_components.uploadable.url_generator.api/public/temporary`, `silverback.api_components.refresh_token.storage.doctrine`, `silverback.helper.route_generator`, `silverback.helper.timestamped_data_persister`, and all `silverback.doctrine.repository.*` IDs.

**DependencyInjection**: `SilverbackApiComponentsExtension` and compiler passes now use `findDefinition()` (which resolves aliases) instead of `getDefinition()` when looking up services by class name, consistent with the new alias pattern.

## alpha.2
- Added getters and setters for `ComponentPosition::$pageDataProperty`
- `RouteGenerator::createFromPage` refactored to `RouteGenerator::create`
- `RouteGenerator::create` now accepts any object implementing new `RoutableIterface`
- Added `RouteGeneratorInterface`
- Added `RoutableInterface`
- Add `RouteGeneratorInterface` alias to service `silverback.helper.route_generator`
- Bug fix - RouteGenerator allow for old entity not to have a route index
- Bug fix - route generator will also persist timestamp fields
- Bug fix - mapping of parentRoute. Change to Many-To-One from One-To-One.
- ComponentPositionNormalizer - do not normalize if no request
- Simple use of parentRoute of pageData (data structure needs a re-think though - parent routes, parent pages and how they should work)
- Bug fix: Only apply doctrine extension for routes to the route resources
- Bug fix: Return expired jwt cookie on logout
- Feature: Added a `location` property to ComponentGroup resource so reusable page templates can decide which collection to display where
- Feature: Return roles (and hierarchical roles granted) with users from /me route
