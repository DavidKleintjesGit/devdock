# Architecture guidelines

> **Scope:** All of David's Laravel projects
> **Last reviewed:** 2026-11-04
> **Source of truth:** no-time-api (to become its own package)

DDD + Action + Repository + Policy. Not strict DDD — no aggregates. Pragmatic over pure: every rule here exists because it removes a decision, not because a book prescribes it.

The layer order is **Controller → Action → Repository**. Nothing skips a layer. An action that has to decide something asks a **policy**, which stands beside the action and never below it. Naming conventions for the classes named here are in `code.md`; how they are tested is in `testing.md`.

## How we think

Objects go in, entities come out. A `*Object` is what you hand to the system; an entity is what it hands back. An entity is something that *is* — it came out of a repository, so it is a fact. Build one yourself and you are holding a claim instead, which is why it never travels back inwards.

An entity knows about itself: its state, its labels, its own answers about its own condition. An action knows what happens between things. Knowledge that travels with a thing lives on the thing; knowledge that describes a process lives in an action.

The same instinct holds one level up. A controller knows HTTP, an action knows the process, a repository knows storage, a policy knows the rule, an entity knows itself, a view knows presentation — and nothing knows two of those.

A rule is a decision that has to come out the same way wherever it is made: whether a branch may be deleted, whether a round passed, whether a working copy is ready. It gets one home, a policy, so it is written once, read in one place, and tested without anything around it.

## Controllers

- **Thin, always.** The whole responsibility is: validate, call `*Object::from(...)`, call an action, return a response.
- **Only CRUD method names** are allowed: `index`, `show`, `store`, `update`, `destroy`, `create`, `edit`. An endpoint that does not fit one of those on the current controller gets its own controller — never a custom method name.
- **Never map request fields onto an object one by one.** That belongs in the object's `from()` factory (see `## Objects and entities`).
- **API controllers** return `$entity->toViewArray()`, or `$collection->toViewArray()` for a collection. Never the raw entity, never `$model->toArray()`.
- **Web controllers** pass the entity or collection straight to the view. Flattening to an array before the view forces string-key access in Blade and throws away the type (see `code.md`, `## Prefer object access over array access`).
- Mapping a collection by hand in a controller is always wrong — `toViewArray()` on the collection already does it (see `## Repositories`).

## Views

- **Blade displays, nothing else.** No `@php` blocks in page views or partials.
- No computing labels, tone or colour mappings, formatted dates, prices, or grouped and transformed data in a template.
- Any derived value belongs on the entity or on the relevant `*Enum` as a method — `$orderEntity->statusLabel()`, `$orderEntity->priceFormatted()`, `$orderEntity->statusTone()`. The controller or action hands the view data that is already display-ready.
- **One exception:** small reusable components in `resources/views/components/**`. A component resolving its own `tone` prop to CSS classes is encapsulated presentation, not page-level logic.

## Actions

- Every action extends a shared base action class that gives it a static `execute()` entry point. The logic lives in `handle()`.
- **One action, one responsibility** — the "and" test from `## How we think`, applied here.
- **The constructor receives input data only** — a `*Object`, a `*Id`, or primitives. Repositories are parameters of `handle()`, where the container resolves them. A repository in the constructor is wrong.
- **Accept only `*Object` and `*Id` types.** Never invent a wrapper (`*Options`, `*PatchData`) to smuggle extra parameters past this rule.
- **An action gathers the facts and acts on the outcome.** It calls the repositories, reads config and the clock, hands what it found to a policy for every decision a repository is forbidden to make, and carries out what the policy decided (see `## Policies`). The rule itself is never written out inside an action.
- An action returns an entity or an entity collection as-is. It never unwraps a collection.

## Policies

- **A policy holds one rule: facts in, an outcome out.** It lives in `<Domain>/Domain/Policies/` and has one public method, `decide()`.
- **A policy is pure.** No repository, no Eloquent, no config, no `now()`, no container, no facade, no file, no process, no log. Everything it needs comes in as a fact, the moment included. The same facts give the same outcome, always.
- **The facts come in as one `*Object`** — `BranchFactsObject`, `ReadinessFactsObject` — built by the action from what the repositories returned.
- **The outcome is an `*Enum` that names it**, never a bare `bool`: `BranchCleanUpEnum::UNPUSHED_WORK` says why, `false` does not. The text a user reads comes from `label()` on that enum. When an outcome has to carry data along, it is a `*DecisionObject` holding the enum and that data.
- **A fact that is expensive may be missing.** It is nullable, and `null` means "not looked at". The policy turns that into an outcome of its own, such as `UNCHECKED`; it never guesses. Only when a fact is too expensive to gather every time does the policy answer in two steps: first which fact it still needs, then the decision.
- **A policy is named after the rule, never after a model.** `BranchCleanUpPolicy`, not `WorktreePolicy`: Laravel registers a class named after a model plus `Policy` as that model's authorization policy, and a rule would silently become a gate. Authorization stays with the framework's own policies.
- **A policy is tested without anything around it**: no database, no fake, no temporary folder. Build the facts, call `decide()`, assert the outcome — one test per outcome.

```php
// the action gathers the facts and acts; the policy decides
$branchCleanUp = resolve(BranchCleanUpPolicy::class)->decide(new BranchFactsObject(
	isCreatedHere: $worktreeEntity->branchCreated,
	pullRequestState: $pullRequestRepository->stateFor($worktreeEntity->branch),
	unpushedCommits: $worktreeRepository->countUnpushed($worktreeEntity->path),
));

if ($branchCleanUp === BranchCleanUpEnum::DELETE) {
	$worktreeRepository->deleteBranch($worktreeEntity->path, $worktreeEntity->branch);
}
```

## Objects and entities

- **`*Object` is the input carrier for writes.** All properties `readonly`, extends the shared value-object base class, no identity and no state.
- A `*Object` has exactly one static factory: `from(array $data, array $uploadedFiles = [])`. Straight field mapping and type conversion — no conditionals, no defaults standing in for business rules.
- For a patch or update, the caller merges first: `*Object::from(array_merge($entity->toFormData(), $request->validated()))`.
- **`*Entity` represents something that already exists**, with identity and state. It always comes out of a repository, has `toViewArray()`, and may carry domain logic.
- **Never construct an entity to pass it into a repository or action.** An entity you built yourself is not a fact from the domain.
- **Uploaded files are always a typed file collection object**, never a raw array. `from()` builds the collection from the raw `$uploadedFiles` argument; no caller assigns files afterwards.

## ID value objects

- **No raw `int`, `?int`, or `string` UUID anywhere an ID is passed** — not in a `*Object`, not in a `*Entity` property, not in an action parameter, not in a repository signature.
- A UUID is still a raw ID. `string $orderUuid` is exactly as wrong as `int $orderId`.
- Every `*Id` has the same shape: `public readonly ?int $id` and `public readonly ?string $uuid`, extending the shared value-object base class. Both nullable, so a caller constructs with whichever it holds.
- The `*Id` class does not exist yet? Create it. There is no case where a raw ID is the lesser evil.

## Repositories

### Structure

- Contract in `Application/Contracts/`, implementation in `Infrastructure/Repositories/`.
- **Eloquent models are internal to the repository** and aliased as `*Eloquent` on import, so the boundary is visible at the top of the file.
- **`mapToEntity()` is the only exit** from Eloquent to the domain.
- **Each domain binds its own contracts in its own service provider**, at `<Domain>/Infrastructure/Providers/`, registered wherever the framework registers providers. Which repository implements which contract is never a cross-domain question, so it never goes in a global list.
- The application-wide service provider keeps only what is genuinely app-wide: morph map, macros, view composers.

### Method signatures

- `create(*Object): *Entity` — receives the input object, returns the new entity.
- `update(*Id, *Object): *Entity` — receives identifier and new data. **Never receives a `*Entity`.**
- `getBy*()` and `getAll()` return a `*EntityCollection`, or an `array` of `*Entity`. Never a raw Eloquent model or collection.
- Every contract method carries a PHPStan-compatible docblock wherever the return type needs one — `/** @return list<OrderEntity> */`.

### Entity collections

- Multiple entities come back as a typed `*EntityCollection`, never a framework collection. The class lives in `<Domain>/Domain/Objects/` and extends the shared value-object collection base class.
- **The repository builds the collection internally.** No code outside it may unwrap with `->values()->all()` or map the entities by hand — that discards the type guarantee the collection exists to provide.
- Actions return the collection as-is; controllers call `toViewArray()` on it (see `## Controllers`).

### What a repository may not do

- **No cross-repository calls.** A repository reaches its own data store and nothing else. When a response needs data from two domains, the action calls both — each call a separate step in `handle()`.
- **No business logic and no config reads.** A repository fetches and persists. A threshold, a time window, or any derived value is computed by the action and passed in as a parameter.
- **No filtering that encodes a rule.** Querying `where('created_at', '>=', $since)` is fine when the action supplied `$since`; deciding that `$since` is six months ago is not.
- **No decision and no text for a user.** Whether something may happen, which state a record ends in, what a message says: a policy decides it and its outcome enum words it. A repository that refuses throws an exception carrying what it ran into, never a sentence for the screen.
- **The same holds for a repository over git, the disk, a process or an external API** as for one over a database: it reads and writes that store, and nothing more.

## Events and listeners

- Use events **sparingly**, and only for **cross-domain** moments — one domain must react to something in another without the first depending on the second. Within one domain, sequence with an orchestrating action instead.
- **The criterion is atomicity, not timing.** Ask: if this fails, must the whole action be undone? Yes → it stays in the action, inside the transaction. No → it is an event. A notification that cannot be delivered may not undo the order it was announcing.
- An **event** is a plain immutable data object — `readonly` properties, usually a `*Id`, no behaviour. It lives in `<Domain>/Domain/Events/` and is named as a past-tense fact: `OrderPaid`. No type suffix (the one exception to `code.md`, `## Naming`).
- A **listener** is thin. Its only job is to call an action. It lives in `<Domain>/Application/Listeners/`, named after the reaction it performs: `SendInvoiceOnOrderPaid`. No type suffix. Business logic in a listener is always wrong — move it into the action.
- **Dispatch with the `event()` helper after the `DB::transaction()` block**, never inside it. Plain statement order, not `DB::afterCommit()` — that behaves differently inside the transaction every test runs in (see `testing.md`, `## Database state`).
- **Register listeners explicitly** in the `$listen` map of the event service provider. Auto-discovery does not reach a domain directory outside the framework's default application path, and one map answers "who else reacts to this moment?" in a way scattered `Event::listen()` calls never will.
- **Never enable event discovery.** It removes exactly that overview.
- Keep events **synchronous** for anything touching money or consistency. Do not queue them.

## Absolutes

- No Eloquent outside a repository. Not in an action, not in a controller, not in a view, not in a command.
- No raw arrays passed between controller, action, and repository. Typed objects at every hand-off.
- No layer skipped. A controller that calls a repository is a bug even when it works.
- No decision outside a policy. A repository fetches and persists, an action gathers and acts, a policy decides.
