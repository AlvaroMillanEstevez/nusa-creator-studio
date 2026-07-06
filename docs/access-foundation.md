# Roles, plans and generation access

Nusa Creator Studio currently stores access state directly on each user. This is an internal MVP foundation, not a billing or permission system.

- Roles: admin, internal_creator, subscriber, free_user
- Plans: free, starter, creator, pro, internal_unlimited
- **is_unlimited** is the authoritative switch for zero-charge generation access.
- **monthly_credit_allowance** describes the plan allowance; automated monthly renewal is not implemented.

Normal accounts pay the configured internal credit cost for each generation. Unlimited accounts may generate with a zero balance: their balance stays unchanged, the Generation stores **credits_charged = 0**, and a linked usage transaction stores **amount = 0** with **metadata.unlimited_access = true**.

For local development only, promote an existing user with:

~~~bash
php artisan nusa:user-access creator@example.test --role=internal_creator --plan=internal_unlimited --unlimited
~~~

The command validates roles and plans, has no HTTP endpoint, does not run automatically, and refuses to run outside local or testing environments.