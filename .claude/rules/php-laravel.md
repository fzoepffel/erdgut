# PHP & Laravel Standards

Applies to: `**/*.php`

- Target PHP 8.3; use modern syntax (enums, readonly, match, etc.)
- Follow Laravel conventions: Eloquent models, FormRequests, service classes
- Run `composer format` after edits; Pint config is in `pint.json`
- Prefer type hints and return types on new code

## Structure

- Controllers: thin, delegate to services
- Business logic: `app/Services/`
- Models: relationships, scopes, accessors; no heavy logic
