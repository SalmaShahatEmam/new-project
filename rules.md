
# Laravel Project Rules

## Project Context
- This project uses Laravel and PHP.
- Follow the existing project architecture.
- Inspect existing code before making changes.
- Reuse existing services, traits, and helpers where appropriate.

## Coding Standards
- Follow PSR-12.
- Use Form Requests for request validation.
- Use API Resources for API responses.
- Keep controllers thin.
- Move complex business logic into Services or Actions.
- Use dependency injection instead of unnecessary direct instantiation.
- Follow existing naming conventions.

## Database Rules
- Use migrations for schema changes.
- Never delete or modify existing production data without approval.
- Add appropriate foreign keys and indexes.
- Check existing records before adding NOT NULL constraints.
- Use transactions when multiple database changes must succeed together.

## Security
- Never expose secrets, passwords, or API keys.
- Validate and authorize every sensitive operation.
- Use policies or gates where appropriate.
- Never trust client-supplied user IDs or prices without verification.
- Protect payment webhooks against duplicate processing.



## Workflow
1. Inspect relevant files.
2. Explain the proposed approach for complex changes.
3. Make the smallest necessary changes.
4. Check for regressions.
5. Report changed files and test results.
6. Ask before destructive operations or major architecture changes.