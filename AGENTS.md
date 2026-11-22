# Project Guidelines

## Context and references
- This is a Laravel 12 / PHP 8.2 application. Follow the architecture and conventions already used in the relevant feature; inspect neighboring files before changing code.
- See [README.md](README.md) for project setup, module generation, API documentation, and analysis commands; see [rules.md](rules.md) for the existing workflow and security requirements; see [SKILLS.md](SKILLS.md) for CRUD-generation notes.
- Treat existing examples as project-specific guidance, not proof that every module follows one uniform pattern.

## Architecture
- CRUD API modules commonly use a controller, Form Request, API Resource, model, repository Contract, and repository Concrete. Contracts and Concretes live under `app/Repositories/Contracts` and `app/Repositories/Concretes`; `RepositoryServiceProvider` binds them.
- Controllers commonly extend `App\Http\Controllers\Api\BaseApiController`, inject the matching repository Contract, and pass the Resource class to the parent constructor. Use this pattern for matching CRUD endpoints; do not force it onto unrelated flows.
- Keep endpoint-specific validation in Form Requests and response shaping in API Resources. Use the existing API response helpers and localized message conventions.
- Put substantial or reusable business operations in the existing `app/Actions` or `app/Services` patterns rather than growing controllers or repositories with unrelated responsibilities.
- For a suitable CRUD module, inspect `CreateConcreteModuleCommand` and its stubs, then use `php artisan app:generate-module {modelName} {namespace}` where appropriate. Check what it generates and changes before running it; it may create a migration and update permission-related data.

## Domain and API conventions
- Preserve established route/version namespaces and file organization under `routes/` and `app/Http`.
- Many user-facing fields are bilingual (`en` and `ar`) and use Spatie Translatable. Check the model, request rules, resources, and language files before changing localized data.
- Follow the existing authentication guards, authorization/permission middleware, API response shape, and resource serialization for the endpoint being changed. Do not trust client-supplied ownership, prices, or other sensitive values.
- Use migrations for schema changes. Preserve existing data, use appropriate constraints and indexes, and use transactions when a multi-step database operation must succeed or fail as a unit.

## Implementation workflow
1. Inspect the related route, controller, request, resource, model, repository, and tests before editing.
2. Make the smallest complete change that fits the existing design. Avoid unrelated cleanup and new abstractions unless the feature needs them.
3. Add or update focused tests for behavior changes; run the narrowest relevant tests, then report commands and results.
4. Ask before destructive data changes or significant architecture changes. Never expose secrets or bypass validation and authorization.
