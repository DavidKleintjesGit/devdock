# Testing guidelines

> **Scope:** All of David's Laravel projects
> **Last reviewed:** 2026-08-24
> **Source of truth:** no-time-api (to become its own package)

How tests are written, named, and asserted. Code style inside a test follows `code.md` — strict types, typed closures, no aligned array keys, no docblocks in factories. What a test is allowed to do to the database is in `database.md`.

## Default test type

- **Write a feature test unless there is a reason not to.** Most behaviour should be exercised end-to-end through the HTTP or console boundary, because that is where it runs.
- Unit tests are for pure logic with no framework dependency: calculations, parsing, transformations.
- Unsure which applies? Feature test.

## Naming

- **The pattern is `{subject}_{action}_{description}`.** Subject first, then the verb, then the condition or outcome. Keep the `/** @test */` annotation; no `it_` prefix and no `test` prefix.
- **The subject is the entry point being tested** — the method, property, or variable the assertion is directly about. It is never optional: `excludes_unpaid_invoices` leaves the reader guessing what excludes them, the component or the query or the rows.
- Use the **method name** when testing what a method produces overall — `render_filters_by_status`, `apply_filters_resets_page`.
- Use a **variable name** only when the test targets one specific named output — `rows_excludes_unpaid_invoices`, `cache_is_filled_after_first_render`.
- A specific DTO or model property is its own subject — `order_status_dto_returns_correct_label`. A name like `order_returns_correct_label` is too vague to place.
- **Name the subject after what you retrieved, not the string you searched by.** Finding a menu item by its label gives you a menu item: `invoice_menu_item_is_hidden_for_guest`, not `invoices_is_hidden_for_guest`. Same rule as `## Variable naming`.
- **Write test names in English.** The one exception is an existing class already written in Dutch — match it rather than mixing languages in one file.
- Test class names mirror the class under test plus a `Test` suffix.

## Structure

- **Arrange → Act → Assert, in that order, with a blank line between the three phases.** The blank lines are the structure; `// Arrange` comments add nothing when each phase is one line.
- **Assertions never sit between arrange steps.** A test that arranges, asserts, then arranges again is two tests sharing a name.
- A test with no visible act phase is asserting on setup, not on behaviour.

## Test scope and ordering

- **One test, one reason to fail.** Never exercise multiple routes in one test method — when a combined test fails you cannot tell which route broke without reading the stack trace.
- **Order test methods by CRUD route:** `index` → `show` → `create` → `store` → `edit` → `update` → `destroy`. Non-CRUD utility routes go last.
- **Within each action, the unauthenticated test comes immediately before the authenticated one.** Every permission check is its own test, not a loop over routes.
- Following a redirect, or a verifying `GET` after a `POST`, keeps the subject a single route.

## Coverage shape

For every meaningful behaviour, cover three paths — each as its own test, per `## Test scope and ordering`:

1. **Happy path** — valid input, authorised user, the success outcome.
2. **Failure path** — invalid input, authorisation denied, business rule violated. Assert the specific error or status, never merely "not 200".
3. **Edge path** — the boundaries: empty collection, maximum length, missing optional field, a value exactly on the limit.

Not every method needs all three. A feature with only a happy path is not finished.

## Factories

- **Always create test data through the factory** — `Order::factory()->create([...])`. Never `Model::create([...])`. No factory yet? Write it before writing the test.
- **Set every test-relevant attribute explicitly in `create()`**, even when the factory default happens to be the value you need. A test that passes because of a default fails for the wrong reason the day the default changes.
- **Everything that does not decide the outcome stays as the factory generated it.** Do not pin values the test does not care about.
- **Use a named state for a recognisable variation** — `Order::factory()->paid()`, `User::factory()->admin()`. Add the state to the factory once the same override appears in a second test.
- **A state does not hide the subject.** If an attribute decides whether the test passes, it goes in `create()` where the reader can see it, even when the state would set it too. States carry incidental setup; `create()` carries the subject.
- **Relations go through the factory:** `->for()` for a parent, `->has()` for children. Build a relation by hand when a cascade would create more rows than the test needs.
- **Use what the factory returns.** Never follow a factory call with a `Model::query()->get()` to fetch back what you just made.
- Factory style itself — no docblocks, `fake()` over `$this->faker` — is in `code.md`, `## Factories`.

## Faker

- **Prefer fake data over fixed literals for every value that does not decide the outcome.** A model comes from its factory, a DTO from its DTO factory; both already produce fake data, so do not hand-write what a factory generates.
- No factory exists and the value is not a model or DTO? `fake()` in the test.
- **Values that decide whether the test passes are the exception** — set those explicitly (see `## Factories`).
- **`fake()` and `WithFaker` need a booted application.** They are unavailable in a `#[DataProvider]` method and in a unit test extending the PHPUnit `TestCase` rather than the framework one. Construct `Faker\Factory::create()` there. This does not loosen the `fake()` rule in `code.md` — inside a factory, and inside any booted test, `fake()` remains the form.

## Request payloads

- **`POST`, `PUT`, and `PATCH` tests get a private `valid{Model}Request(array $overrides = []): array` helper.** Never build a request array inline in the test method.
- **Helpers live at the top of the class**, above the first `/** @test */` method. They declare what a valid payload looks like, so they belong where a constructor would.
- **The base is `Model::factory()->raw()`.** Related models that must exist as real rows are created with `->create()` inside the helper. Overrides are merged last.
- **One helper serves both `store` and `update`.** Pass an override only for the field the test is actually about.
- **A missing required field is a factory problem, not a helper problem.** Add it to `definition()` with a `fake()` value so `raw()` produces a complete payload. The helper may only add `active => 1` — the boolean default converted to a form-valid integer — and foreign keys from its own `->create()` calls. Never a hardcoded literal.
- **This pattern is for write verbs only.** A `GET` test inlines its factory create; a one-line prerequisite such as creating a share key and passing it as a query param never becomes a helper.
- **A `#[DataProvider]` covers variations of the same assertion** — same act, same expectation shape, different input. Anything else is a separate test.
- **Provider methods are `public static` and run before the application boots.** Literal data only: no `fake()`, no `now()`, no factories, no container. That rules out date-driven variations entirely, since a provider cannot produce a date without hardcoding one (see `## Time and dates`). Either make them separate tests, or have the provider supply a day offset the test body turns into a date.

## Private helper methods

- **Never extract a helper for a simple factory create.** Inline it in each test.
- A helper earns its place only for complex setup reused across several tests.
- **A helper contains factory `create` calls and nothing else.** No lookups, no queries, no retrieving the subject of the test. Wrapping a lookup — `findPaidOrder()`, `getOrder()`, `findMenuItem()` — is always wrong, even when several tests would reuse it. The lookup is the thing under test; it stays visible.

## Variable naming

- **A variable is named for what it holds, not for how it was found.** A menu item found by the label `Invoices` is `$invoiceMenuItem`, not `$invoices`.
- `$order` for a single model or a `count(1)->create()`.
- **`$orders1`, `$orders2`, … for multiple collections in one test.** The number is a positional label, not a count. `$orders` is a merged collection.
- **The number of items belongs in the comment, never in the variable name.**
- Model variables are numbered, not described. `$paidOrder` for the first group is wrong; it is `$order1`, and the comment says what makes it paid.
- **Dates are the exception** — they may be named descriptively, because a test reuses them and a description reads better. The name must start with `date` or `datetime` so the type is obvious: `$dateInRange`, `$datetimeYesterday`. Bare `$inRange` or `$today` is wrong.

## Where to assert

- **Assert at the layer the logic actually lives in.**
- **Filtering, sorting, and selection** are asserted on the data handed to the view — `$response->viewData('orders')`, then `contains()`. `viewData()` throws when the key does not exist, which is a better failure than `assertDontSee` on text that never rendered in the first place.
- **A controller that transforms models into DTOs or arrays** breaks `contains()`. Assert on an identifying field instead, such as a plucked column.
- **`assertSee` and `assertDontSee` are for logic that is genuinely in the Blade view** — a conditional, a null-coalescing fallback, an empty-state message. When both layers matter, assert both.
- **An `assertSee` value identifies the record as uniquely as possible.** An order number, not a word that may already be on the page for another reason — that passes even when the record is absent.
- **Livewire logic in the component** is asserted on the component: `assertSet`, `$component->get()`, `assertViewHas`. Use nested dot notation — `orders.0.status` — so the test also breaks when the array shape or a key name changes.
- A component with an `$orders` property that the test only checks through rendered text is not tested.

## Assertions

- **Read expected values off the factory result, never repeat the literal.** `"{$order->number} - {$order->status}"`, not `'ORD-42 - paid'`.
- **Assert against the origin of the value.** A value the test sent → assert the input you sent. A value the factory generated incidentally → read it back off the model. A value that must stay unchanged → capture it in a variable *before* the operation and assert against that variable after.
- **Capture a scalar copy.** Capturing a mutable object captures a reference, and the operation can change it along with the model.
- **Never assert a model value against itself.** `assertSame($order->total, $order->fresh()->total)` reads like an unchanged-check but Eloquent may hand back the same instance — it passes without the code under test doing anything.
- **Explain an assertion in an inline comment, not in the `$message` argument.** When several factory groups feed one assertion, give the arithmetic: `// $orders1 (3) + $orders2 (2)`.
- **Inclusion and exclusion assertions on numbered models get a comment each**, naming the variable, its distinguishing attribute value, and why it passes or fails the condition: `// $order1 due_date +5d, within due range +4d–+6d`.

## Database state

- **Never add `DatabaseTransactions` to a test class.** It is already in the base `TestCase` — not as a `use` in the class body, not as an import at the top of the file. Deleting models in `setUp()` is unnecessary for the same reason.
- **Never change schema inside a test.** No `Schema::create()`, `Schema::table()`, or `Schema::drop()`. Schema comes from migrations; a table invented in one test file is a table nothing else knows about, and it outlives the transaction that isolates everything else. A missing table means a missing migration.
- Everything else about destroying data is in `database.md`.

## External calls — never real

- **A test never reaches the network.** Fake the boundary, and assert against the fake when the call itself is the behaviour under test.
- `Http::fake()`, or `Http::fake(['api.example.com/*' => Http::response([...], 200)])` for one host.
- `Mail::fake()` with `Mail::assertSent()`; `Notification::fake()` with `Notification::assertSentTo()`.
- `Queue::fake()` to assert a job was dispatched without running it. `Storage::fake('local')` for files.
- **Our own API clients** get a fake bound in `setUp()` via `$this->app->bind()`, or a facade so `ClientFacade::fake()` works.
- **API tokens in `phpunit.xml` stay empty strings.** A test that passes only because a real token is present is not a test.

## Time and dates

- **Never hardcode a date**, not even as a placeholder for "some non-null value". A literal like `'2030-01-01'` becomes meaningless over time and breaks silently the day an `after:today` rule appears. Derive from `now()`.
- **Accumulate the moment before reading multiple fields from it** — the full rule is in `code.md`, `## Accumulate Carbon before using multiple fields`. It applies to `create()` arrays and to `assertDatabaseHas()` alike.
- Date variable names are covered in `## Variable naming`.

## Anti-patterns

- **No always-true assertions.** If the test would pass with the code under test removed, it tests nothing. Watch for the IDE warning about comparing a value with itself (see `## Assertions`).
- **No dead asserts.** `$this->assertTrue(true)` is noise. A test with nothing to assert has nothing to test.
- **No interlocked tests.** Every test passes alone, in any order. One that only passes after another ran is coupled through leftover state.
- **No branching in a test.** No `if`, `else`, `match`, or ternary in a test body. A branch is two outcomes under one name — make it two tests, or a `#[DataProvider]`.
- **No skipping or deleting a test to get CI green.** A failing test is the test doing its job. `markTestSkipped()` to unblock a deploy is the deploy owner's call, not a fix.
- **No asserting on rendered text when the logic sits in the data layer** (see `## Where to assert`).

## Configuration during test runs

- **`phpunit.xml` is the source of truth for the test environment.** API tokens, mail drivers, and queue drivers are set there to safe values so a test cannot reach anything real.
- **Never run `php artisan config:cache` before a test run**, locally or in CI. Cached config freezes whatever env values existed when the cache was written, and the suite then runs against those instead of `phpunit.xml`.

## Running the right tests

- Work outwards: the file you are editing, then the feature around it, then the full suite before handing the work over.
- `php artisan test --filter=test_method_name` for one test; a path for one file; bare `php artisan test` for everything.
- **`--parallel` is for the full suite only**, and only where the project is set up for it — parallel runs need a database per process, which `DatabaseTransactions` on a single connection does not provide.
