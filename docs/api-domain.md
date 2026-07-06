# API domain foundation

Nusa Creator Studio currently has four core creator-domain entities. All records are owned by a user.

## Entities

- **Project** groups a creator workflow. The type and status fields are strings so product workflows can evolve without an early database enum. The input payload stores the submitted brief and settings stores workflow options.
- **MediaAsset** records a file stored through a Laravel filesystem disk. It can optionally belong to a project; metadata holds media-specific details such as dimensions or duration.
- **Generation** records an asynchronous generation attempt. It stores input/output snapshots, lifecycle timestamps, provider cost, charged credits, and a safe failure message. The provider remains nullable until provider integrations are introduced.
- **CreditTransaction** is an append-oriented internal platform credit ledger entry. Its type determines whether credits are added or spent; amount and balance after are always non-negative.

## Relationships

A user has many projects, media assets, generations, and credit transactions. A project has many media assets and generations. A generation can have many credit transactions.

The project links on media assets and generations, and the generation link on credit transactions, are optional and become null if the parent is deleted. User-owned records are deleted when their user is deleted.

JSON columns are cast to PHP arrays by the Eloquent models. Generation cost uses decimal(12, 4) and is returned by Eloquent as a four-decimal string to avoid floating-point loss. Generation API responses expose input_payload and output_payload; temporary equest_payload and esult_payload aliases preserve compatibility with existing clients.

## Internal platform credits

Credits are internal platform credits consumed by Nusa Creator Studio features such as campaigns, videos, voiceovers, and captions. They are not payment cards and do not represent a card balance.

`users.credits_balance` starts at 20 and never drops below zero. Before starting a generation, the application must atomically verify that the user has enough credits; a user with insufficient credits cannot start that generation.

`CreditTransaction.type` determines direction:

- `purchase`, `plan_renewal`, `admin_grant`, and `refund` add credits.
- `usage` and `expiration` spend or remove credits.

`amount` is always zero or positive and records the magnitude of the change. `balance_after` is the final non-negative user balance. `Generation.credits_charged` is likewise always zero or positive.

## Project API

Project CRUD is available under `/api/v1`:

- `GET /projects` returns the current user's projects as a paginated collection.
- `POST /projects` creates a project.
- `GET /projects/{project}` returns one owned project.
- `PATCH /projects/{project}` updates an owned project.
- `DELETE /projects/{project}` deletes an owned project and returns `204 No Content`.

Accepted project types are `affiliate_campaign`, `video_enhancer`, and `product_to_video`. Accepted statuses are `draft`, `pending`, `processing`, `completed`, and `failed`; newly created projects default to `draft`.

Project, generation, and media routes require Sanctum authentication. Lookups are owner-scoped and return `404` for resources owned by another user.
## Async generation jobs

Generation lifecycle statuses are `queued`, `processing`, `completed`, and `failed`. Legacy `pending` records remain readable and are treated as queued work.

The three existing mock workflows dispatch queue jobs:

- `GenerateAffiliateCampaignJob`
- `GenerateVideoEnhancerJob`
- `GenerateProductVideoScriptJob`

A generation request creates a queued generation and charges its configured tool cost before dispatch. The job marks the generation processing, runs the existing deterministic mock provider, then stores the output and marks it completed. Jobs receive only the generation ID, and repeated processing does not charge credits again.

If an internal provider job fails, the generation is marked failed with a generic safe message. Positive charges are returned once with a `refund` credit transaction linked to the failed generation. Unlimited users and zero-cost tools are charged zero and therefore do not need a refund transaction.

Authenticated generation endpoints are:

- `POST /api/v1/projects/{project}/generate-campaign`
- `POST /api/v1/projects/{project}/generate-video-enhancer`
- `POST /api/v1/projects/{project}/generate-product-video-script`
- `GET /api/v1/generations/{generation}`
- `GET /api/v1/projects/{project}/generations`

The detail and history endpoints are owner-scoped and return `404` for another user's generation or project.

Run a local worker with:

```bash
cd apps/api
php artisan queue:work
```

With `QUEUE_CONNECTION=redis`, the worker must be running or generations remain queued. With `QUEUE_CONNECTION=sync`, jobs complete during the request, which is useful for tests and lightweight development.
