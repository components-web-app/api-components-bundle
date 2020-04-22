# Configuration

> This reference is the default configuration values without the [recommended configuration](https://github.com/silverbackis/recipes-contrib/blob/api-component-bundle/silverback/api-component-bundle/2.0/config/packages/silverback_api_component.yaml) applied (which is done so by default if you use Symfony Flex)

Full bundle configuration reference:
```yaml
silverback_api_component:
    website_name:         ~ # Required
    table_prefix:         _acb_
    security:
        tokens:               []
    enabled_components:
        form:                 true
        collection:           true
    user:
        class_name:           ~ # Required
        email_verification:
            enabled:              true
            email:
                redirect_path_query:  ~
                default_redirect_path: ~ # Required
                subject:              ''
            default_value:        ~ # Required
            verify_on_change:     ~ # Required
            verify_on_register:   ~ # Required
            deny_unverified_login: ~ # Required
        password_reset:
            email:
                redirect_path_query:  ~
                default_redirect_path: ~ # Required
                subject:              ''
            repeat_ttl_seconds:   8600
            request_timeout_seconds: 3600
        emails:
            welcome:
                enabled:              true
                subject:              'Welcome to {{ website_name }}'
            user_enabled:
                enabled:              true
                subject:              'Your account has been enabled'
            username_changed:
                enabled:              true
                subject:              'Your username has been updated'
            password_changed:
                enabled:              true
                subject:              'Your password has been changed'

```