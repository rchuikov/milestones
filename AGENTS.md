# MyMilestones

Laravel 13 / PHP 8.5 application.

## Development environment

This project runs through Laravel Sail.

Use Sail for project commands:

- `./vendor/bin/sail artisan ...`
- `./vendor/bin/sail composer ...`
- `./vendor/bin/sail npm ...`
- `./vendor/bin/sail pint`

Do not run PHP, Composer, Artisan, npm, or Node directly on the host unless explicitly asked.

## Project style

Prefer simple, direct Laravel solutions.

Do not introduce abstractions, interfaces, repositories, DTOs, service layers, or helper classes unless they solve an actual repeated or complex problem.

Avoid premature generalization and speculative future-proofing.

Reuse existing code when the same logic is genuinely repeated.

Prefer framework-native Laravel conventions unless the existing project deliberately uses another pattern.

## Eloquent

Keep Eloquent models thin.

Models should primarily contain:

- attributes
- casts
- relations
- small model-local invariants when appropriate

Do not move general business workflows into models.

## Validation

Use FormRequest classes for complex HTTP input validation, normalization, or request-level authorization. Simple validation may remain directly in controllers.

Use `prepareForValidation()` when request data needs normalization before validation.

Services should receive validated values, models, or domain data — not Request objects.

## Services

Use services for real business operations, especially when an operation:

- affects multiple models
- requires a transaction
- contains non-trivial state transitions
- would otherwise make a controller noticeably complex

Do not create services for trivial one-line operations.

## Controllers

Keep controllers focused on HTTP concerns:

- authorization / ownership checks
- calling services
- selecting views
- redirects and responses

Simple persistence operations may remain directly in controllers when no business logic is involved.

## Transactions

Use `DB::transaction()` for multi-step operations that must be atomic.

Do not assume Eloquent model events automatically provide transaction boundaries.

## Blade

Reuse existing Blade components where practical.

Do not duplicate UI patterns that already exist as components.

Keep presentation-only calculations in the view when they are simple and local to that view.

Avoid moving pure display formatting into controllers.

## Frontend

Use the existing Tailwind CSS conventions.

Use Heroicons through Blade components, for example:

`<x-heroicon-o-clock class="size-4"/>`

Use the existing Vite setup.

For timer/date calculations in JavaScript, use `@js-temporal/polyfill` rather than custom calendar arithmetic.

## Code style

All non-abstract classes must be declared `final`.

Follow the existing code style.

Run `./vendor/bin/sail pint` before considering PHP changes complete.

## Git

Use Conventional Commits.

Examples:

- `feat: add milestone history`
- `fix: correct milestone duration display`
- `refactor: simplify milestone service`

Do not commit, amend, rebase, push, force-push, or rewrite history unless explicitly asked.

Do not create additional commits when the requested change is clearly part of an unfinished atomic change already being worked on.

## Migrations

Keep migrations self-contained.

Do not depend on application models or enums inside migrations.

Do not rewrite committed migrations unless explicitly asked.

For new schema changes after a migration has been committed, create a new migration.

## Current domain

A milestone belongs to a user.

A milestone has one or more periods.

A period contains:

- `started_at`
- nullable `finished_at`

An active period has `finished_at = null`.

A milestone may have at most one active period.

State operations:

- Start: create a new active period
- Stop: finish the current active period
- Reset: finish the current active period and immediately create a new active period using the same timestamp

Milestone history is currently read-only.

## Ownership

Users may only access their own milestones.

Ownership checks return 403 for another user's milestone. Missing milestones return 404 through route model binding.

For updates, check ownership in `MilestoneRequest::authorize()` before input validation. Other milestone actions use controller-level ownership checks.

Do not replace this with Policies or another authorization architecture unless explicitly requested.

## Existing project direction

The project favors:

- thin Eloquent models
- FormRequest for validation
- services for non-trivial business operations
- simple Blade views
- minimal abstraction
- explicit, readable code

When modifying existing functionality, first inspect the current implementation and follow its style rather than inventing a parallel architecture.
