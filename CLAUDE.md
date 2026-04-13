# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Primary Goal

This Mage-OS backend is being integrated with a **Daffodil Angular storefront** (`develodesign/dai-builder`). All development should consider GraphQL exposure and Daffodil driver compatibility on both the Magento (PHP/GraphQL) and Angular (TypeScript/NgRx) sides.

The **`magento-daffodil-integration` agent** (`~/.claude/agents/magento-daffodil-integration.md`) is the primary agent for this work — invoke it automatically for any cross-stack task. It covers driver architecture, GraphQL schema extension, transformer patterns, NgRx wiring, and full verification checklists.

The `magento-daffodil` skill (`~/.claude/skills/magento-daffodil/SKILL.md`) provides supplementary reference material for the same integration domain.

## Overview

This is a **Mage-OS 2.2.1** (Magento 2 fork) e-commerce platform project running in a GitHub Codespaces dev container. It includes the full Mage-OS Community Edition with sample data and optional Hyvä theme support.

**Stack:** PHP 8.3, MariaDB 10.6, Redis, OpenSearch 2.x, Nginx, PHP-FPM — all managed by Supervisor inside the container. Mailpit (email), phpMyAdmin, and OpenSearch run as Docker-in-Docker containers.

## Common Commands

### Magento CLI (use `mage` alias or `bin/magento`)
```bash
# Cache operations
bin/magento cache:flush
bin/magento cache:clean

# Reindex
bin/magento indexer:reindex

# Dependency injection compilation
php -d memory_limit=-1 bin/magento setup:di:compile

# Module management
bin/magento module:enable <ModuleName>
bin/magento module:disable <ModuleName>

# Config
bin/magento config:set <path> <value>
bin/magento config:show <path>

# Setup upgrade (after adding new modules or running sample data)
php -d memory_limit=-1 bin/magento setup:upgrade

# Deploy static content
php -d memory_limit=-1 bin/magento setup:static-content:deploy -f

# Hyvä theme assets (requires HYVA_LICENCE_KEY)
n98-magerun2 dev:theme:build-hyva frontend/Hyva/default
```

### Composer (always use with increased memory)
```bash
php -d memory_limit=-1 $(which composer) <command>
```

### Services
```bash
# Check status of all services
.devcontainer/scripts/status.sh

# Restart services via Supervisor
sudo supervisorctl reread && sudo supervisorctl update

# Service logs
sudo supervisorctl tail nginx
sudo supervisorctl tail php-fpm
```

### Code Quality
```bash
# PHP CodeSniffer (Magento coding standard)
vendor/bin/phpcs --standard=Magento2 <path>
vendor/bin/phpcbf --standard=Magento2 <path>

# PHPStan
vendor/bin/phpstan analyse <path>

# PHP CS Fixer
vendor/bin/php-cs-fixer fix <path>

# PHPMD
vendor/bin/phpmd <path> text ruleset.xml
```

### Testing
```bash
# Unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml

# Single test file
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml <path/to/Test.php>

# Integration tests (requires DB setup)
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml
```

## Architecture

### Directory Structure
- `app/code/` — Custom modules (PSR-0 autoloaded)
- `app/etc/` — Environment config (`env.php`, `config.php`, `di.xml`)
- `vendor/mage-os/` — Core Mage-OS modules (do not modify directly)
- `vendor/mage-os/framework/` — Magento Framework
- `lib/internal/Magento/Framework/` — Also framework code (PSR-4 autoloaded as `Magento\Framework\`)
- `generated/` — Auto-generated DI proxies and factories (do not commit)
- `pub/` — Web root (`pub/static/`, `pub/media/`)
- `dev/tests/` — Test suites (unit, integration, functional)

### Module Anatomy
Modules live in `app/code/<Vendor>/<Module>/` or `vendor/<package>/`. Each module requires:
- `registration.php` — registers the module with the component registry
- `etc/module.xml` — declares module name and dependencies
- `etc/di.xml` — dependency injection configuration
- `etc/config.xml` — default configuration values
- `etc/db_schema.xml` — declarative database schema

### Key Architectural Patterns
- **Dependency Injection**: Constructor injection via `di.xml`; factories/proxies are auto-generated in `generated/`
- **Service Contracts**: Interfaces in `Api/` directories define the public API; models implement them
- **Plugins (Interceptors)**: Declared in `di.xml`, modify method behavior without overriding. Always prefer plugins over rewrites
- **Events/Observers**: Declared in `etc/events.xml`
- **Layout XML**: Controls page structure; files in `view/<area>/layout/`
- **Templates**: `.phtml` files in `view/<area>/templates/`; use `$block->escapeHtml()` etc. for output escaping

### Areas
- `frontend` — Storefront
- `adminhtml` — Admin panel
- `webapi_rest` / `webapi_soap` — API areas
- `graphql` — GraphQL area

### Cache & Session
- Application cache: Redis (DB 1), Page cache: Redis (DB 2), Sessions: Redis (DB 0) — configured in `app/etc/env.php`
- Run `bin/magento cache:flush` after any configuration, layout, or template changes
- Run `setup:di:compile` after adding/modifying DI configuration

## Dev Container Environment

### Ports
| Port | Service |
|------|---------|
| 8080 | Mage-OS storefront (public) |
| 8081 | phpMyAdmin |
| 3306 | MySQL/MariaDB |
| 6379 | Redis |
| 1025 | Mailpit SMTP |
| 8025 | Mailpit Web UI |
| 9200 | OpenSearch |

### Environment Variables (in `devcontainer.json`)
- `PLATFORM_NAME` — `mage-os` (default) or `magento`
- `INSTALL_MAGENTO` — `YES` triggers fresh install; `NO` imports existing DB from `env.php`
- `INSTALL_SAMPLE_DATA` — `YES` includes demo catalog/customer data
- `HYVA_LICENCE_KEY` + `HYVA_PROJECT_NAME` — required for Hyvä theme installation
- `MAGENTO_COMPOSER_AUTH_USER` / `MAGENTO_COMPOSER_AUTH_PASS` — for Magento Commerce access

### Lifecycle
- `onCreateCommand` → `setup.sh`: starts Docker containers (Mailpit, OpenSearch, phpMyAdmin), installs AI tooling
- `postAttachCommand` → `start.sh`: configures Nginx/PHP-FPM/Redis via Supervisor, installs Magento if needed (checks for `.devcontainer/db-installed.flag`), sets developer mode, compiles DI, reindexes
- First run takes significant time (Composer install + Magento setup + sample data + reindex)
- Subsequent attaches are fast — services restart and Hyvä assets are rebuilt if licensed

### Installed Global Tools
- `n98-magerun2` — Magento CLI utility (`n98-magerun2 <command>`)
- `mage` bash alias — shorthand for `bin/magento`
- `@anthropic-ai/claude-code` — Claude Code CLI
- `@google/gemini-cli` — Gemini CLI

## Notes
- **Two Factor Auth is disabled** (`Magento_TwoFactorAuth` = 0 in `config.php`) — expected for dev
- Admin URL: `/admin`, credentials set via `MAGENTO_ADMIN_USERNAME`/`MAGENTO_ADMIN_PASSWORD` env vars (default: `admin`/`password1`)
- The `generated/` and `var/` directories are writable by the `vscode` user; `pub/` and web files are world-readable for nginx (`nobody`)
- When modifying vendor code is unavoidable, use plugins/preferences in a custom module — never edit `vendor/` directly
- `app/etc/env.php` is overwritten on each `postAttachCommand` if `INSTALL_MAGENTO=NO`, populated from `.devcontainer/config/env.php`
