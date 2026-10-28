# Code guidelines

> **Scope:** All of David's Laravel projects
> **Last reviewed:** 2026-08-24
> **Source of truth:** no-time-api (to become its own package)

Rules that apply to every line of PHP — production code and tests alike. Where a rule concerns a specific layer, `architecture.md` has the detail; where it concerns a test, `testing.md` does.

## Strict types

- **Every PHP file opens with `declare(strict_types=1)`**, directly under `<?php` and above the namespace. No exceptions, tests included.

## Typehints and return types

- **Every method has explicit parameter types and a return type.** An untyped parameter or a missing `: void` is not a style preference, it is a missing contract.
- **Closures too**, including arrow functions: `fn(Order $order) => $order->touch()`.

## Naming

- **A variable name says exactly what it holds, and the suffix says what type it is.** `$orderEntity`, `$orderObject`, `$orderArray`, `$orderEloquent`.
- **`$order` is wrong** — model, entity, array, object? So are `$data`, `$result`, `$item`, and every other name that survives a copy-paste into any context.
- `$orderEloquent` never leaves the repository (see `architecture.md`, `## Repositories`).
- **Every domain class name ends in a type suffix:** `*Object`, `*Entity`, `*Collection`, `*Contract`, `*Repository`, `*Action`, `*Enum`, `*Controller`, `*Request`. No bare class names.
- **One exception:** events and listeners follow Laravel convention — an event is a past-tense fact (`OrderPaid`), a listener is named after its reaction (`SendInvoiceOnOrderPaid`). Neither takes a suffix. See `architecture.md`, `## Events and listeners`.

## IDs are objects, everywhere

- **An ID is never a raw scalar** — not as an argument, not as a property. `ownerId: int` is wrong even as an entity property; it is `ownerId: UserId`.
- This holds for UUIDs. `string $orderUuid` is exactly as wrong as `int $orderId`.
- Full rule, including the shape of a `*Id` class: `architecture.md`, `## ID value objects`.

## Prefer object access over array access

- **Holding a typed object (`*Entity`, `*Object`, `*Collection`) means reading it with `->property` or `->method()`.** Converting it to an array to read one field throws away the type and forces every consumer to know string keys.
- `$orderEntity->orderId->uuid` and `$orderEntity->status`, never `$orderEntity["id"]` and `$orderEntity["status"]`.
- **Does not apply to data that was never an object**: raw JSON payloads, static config and lookup arrays, request input. Those are genuinely array-shaped.
- The one place an entity becomes an array is `toViewArray()`, at the API boundary (see `architecture.md`, `## Controllers`).

## Accumulate Carbon before using multiple fields

- **Two calls to `now()` can return different values.** When more than one field derives from the same moment, assign the moment to a variable first and read every field off that variable.
- `$nextWeek = now()->addWeeks(1);` then `$nextWeek->isoWeekYear()` and `$nextWeek->isoWeek()` — never `now()->addWeeks(1)` twice in the same array.
- Applies to `create()` arrays, `updateOrCreate()` keys, `assertDatabaseHas()`, and anywhere else two fields must agree about the same instant.
- The name of a date variable starts with `date` or `datetime` in tests (see `testing.md`, `## Variable naming`).

## Factories

- **No docblocks in a factory.** The class name, `$model`, and `definition()` document themselves. The generated `@var string` block above `$model` and the "Define the model's default state" line above `definition()` both come out.
- **Use `fake()`, never `$this->faker`.** `fake()` is the global helper and works in factories, seeders, closures, and anywhere else; `$this->faker` only works inside a factory class and is a bound shortcut for the same thing. Use the canonical form.
- A field that a request must supply belongs in `definition()` with a `fake()` value, so `raw()` produces a complete valid payload (see `testing.md`, `## Request payloads`).
- `fake()` needs a booted application. Where there is none, construct Faker directly — see `testing.md`, `## Faker`.

## Arrays

- **One space around `=>`, never padded to align.** Alignment makes diffs noisy: renaming or adding one key forces every other line in the array to re-indent.
- **More than one key means multiple lines**, one key per line, with a trailing comma on the last.

## Config keys

- **Check every usage before renaming a config key.** A rename that misses one call site returns `null` at runtime with no error and no failing test.
- Search for the full key string, not the last segment.

## Comments and docblocks

A comment describes the thing it sits on — factually, in the domain's words. It is not a place to narrate mechanics or point at the rest of the system.

- **Lead with what this thing is or does**, present tense, in domain terms. The domain moment or the job it represents comes first: `The payer's attempt failed. The payment closes as failed…`, `The single payout gate: releases only when…`, `Routes a parsed Stripe event to the action that handles its type.`
- **Add a "why" only when it is non-obvious and earns its place** — a consequence or a design reason the code does not already state (`so the history shows that someone tried`). When the code says it plainly, write no comment.
- **Describe only the class in front of you.** Never narrate what another class did or will do (`the boundary X::y did the mapping`). A genuinely related concept may be pointed at with `{@see Other}`, but never explain that other's internals.
- **Never describe your own mechanics in the negative** — `no repositories`, `no raw payload digging`, `does nothing else`, `pure dispatcher`. State what the code does, not the absence of plumbing. A *domain* fact that happens to be negative is fine — `No money has moved yet, so there is no transaction` describes the world, not the wiring.
- **Keep the tags.** `@method`, `@param`, `@throws`, `@return` stay; they are the contract, not prose.
