---
layout: default
title: Console Commands
nav_order: 7
has_children: false
---
# Console commands
{: .no_toc }

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Create a User

We have added a few console commands to make is easy to generate users using the Symfony console

Examples:

```sh
bin/console silverback:api-components:user:create new_username
bin/console silverback:api-components:user:create new_username user@email.com password
bin/console silverback:api-components:user:create new_username --super-admin
bin/console silverback:api-components:user:create disabled_user --inactive
bin/console silverback:api-components:user:create existing_username --overwrite
```

If you do not provide all the required parameters, the command will prompt you for answers. However, you will not be prompted for any of the optional flags (e.g. super-admin, inactive or overwrite).

## Purge form cache

Because the database entity will not update when you update the Symfony form php files, you can run this command to update the last modified timestamp in the database which will purge caches. This usually will not be required because when you re-deploy your application, caches are usually purged.

```sh
bin/console silverback:api-components:form-cache-purge
```

## Expire refresh tokens

You can run a command to expire user refresh tokens. If you do not provide a username, all refresh tokens will be expired. If you do not provide a username database field, this will default to `username`

```sh
bin/console silverback:api-components:refresh-tokens:expire
bin/console silverback:api-components:refresh-tokens:expire username
bin/console silverback:api-components:refresh-tokens:expire username database_field
```
