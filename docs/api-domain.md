# API domain foundation

Nusa Creator Studio currently has four core creator-domain entities. All records are owned by a user.

## Entities

- **Project** groups a creator workflow. The type and status fields are strings so product workflows can evolve without an early database enum. The input payload stores the submitted brief and settings stores workflow options.
- **MediaAsset** records a file stored through a Laravel filesystem disk. It can optionally belong to a project; metadata holds media-specific details such as dimensions or duration.
- **Generation** records an asynchronous generation attempt. It stores request/result snapshots, lifecycle timestamps, provider cost, and charged credits. The provider remains nullable until provider integrations are introduced.
- **CreditTransaction** is an append-oriented credit ledger entry. Amount is always non-negative; the type indicates whether it is a credit or debit. Balance after is also non-negative, preventing an overdrawn credit balance.

## Relationships

A user has many projects, media assets, generations, and credit transactions. A project has many media assets and generations. A generation can have many credit transactions.

The project links on media assets and generations, and the generation link on credit transactions, are optional and become null if the parent is deleted. User-owned records are deleted when their user is deleted.

JSON columns are cast to PHP arrays by the Eloquent models. Generation cost uses decimal(12, 4) and is returned by Eloquent as a four-decimal string to avoid floating-point loss.

## Project API

Project CRUD is available under `/api/v1`:

- `GET /projects` returns the current user's projects as a paginated collection.
- `POST /projects` creates a project.
- `GET /projects/{project}` returns one owned project.
- `PATCH /projects/{project}` updates an owned project.
- `DELETE /projects/{project}` deletes an owned project and returns `204 No Content`.

Accepted project types are `affiliate_campaign`, `video_enhancer`, and `product_to_video`. Accepted statuses are `draft`, `pending`, `processing`, `completed`, and `failed`; newly created projects default to `draft`.

The API prefers the authenticated user when one is available. Until authentication is wired to these routes, local and test environments use the first user or create a clearly marked development user. Other environments require an authenticated user. Project lookups are owner-scoped and return `404` for projects owned by another user.
