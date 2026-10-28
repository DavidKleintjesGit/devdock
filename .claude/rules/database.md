# Database guidelines

> **Scope:** All of David's Laravel projects
> **Last reviewed:** 2026-08-24
> **Source of truth:** no-time-api (to become its own package)

Rules about destroying data. They are stricter than the rest of these files on purpose: every other mistake is a commit away from being fixed, and this one is not. Where a rule concerns test isolation instead, `testing.md` has it.

## Running database commands

- **`php artisan migrate:fresh` is never allowed** — not with permission, not on request, not "just locally". It destroys all data with no recovery path.
- The global permission deny list also blocks `migrate:refresh` and `migrate:reset`, for the same reason.
- A local data reset is `php artisan migrate:fresh --seed`, typed by a person who knows which database they are pointed at. It is not something to run on someone's behalf.

<!-- PARKED — inactive, awaiting David's decision (raised 2026-08-24):
Never run any database-altering command without explicit permission from the user first. This
includes `php artisan migrate`, `php artisan migrate:rollback`, and any `php artisan tinker`
command that reads or writes data. Ask before running.
-->

## Ask before writing deleting code

- **The question comes before the code, not before the command.** Permission for `php artisan migrate` protects nothing if the migration hides a loop that empties tables — the person approving the command cannot see what is inside it.
- Before writing anything that deletes, name two things and wait for an answer: **what will be deleted**, and **what bounds it**.
- "I will ask before running it" is not a substitute. By then the blast radius is already written down and looks reviewed.

## Migrations change schema, not data

- **A migration adds, changes, or removes structure.** Nothing else.
- It does not delete rows it did not create, and it never resets the database.
- **A migration cannot rebuild the database it is part of.** Calling `migrate:fresh` from inside a migration drops the `migrations` table the migrator is writing to, then re-runs the migration that called it. Wanting a reset inside a migration is a category error — the reset belongs one level up, with a person.
- **Demo and seed data belong in a seeder**, invoked with `db:seed`. Never in `database/migrations/`: everything in that directory runs unattended, on every deploy, in every environment.
- **The tell that a migration is at the wrong level** is an exception carved out to keep its own machinery alive — `if ($table === 'migrations') continue;`. Stop and move it out; do not make the exception work.

## Never delete data you did not create

- **Deletion is bounded by the identity of the records you made**, never by "every table" or "everything in this database".
- A loop over a table listing, a `DELETE`/`TRUNCATE`/`DROP` across an enumerated set, and a `whereNotNull('id')` catch-all are one mistake wearing three faces: the blast radius is decided by whatever the enumeration returns, not by intent.
- **Seeded records that need to be removable get marked when they are created**, and are deleted by that mark — a demo domain in the email, a flag, a known prefix.
- Deleting through a relation, or letting a foreign key cascade do it, is bounded. Deleting per table is not.

## Never disable foreign key checks

- **A refused delete is the database protecting referential integrity.** `TRUNCATE` failing with error 1701 on a referenced table is the correct outcome.
- `FOREIGN_KEY_CHECKS=0` and `Schema::disableForeignKeyConstraints()` remove exactly the brake that was stopping you. Neither is ever the fix.
- Fix the order of deletion, delete through the relations, or delete less.

## Destructive code needs a hard guard

- **Code that deletes more than a single known record refuses to run unless it can prove where it is.** Not a warning, not a log line — an exception, thrown before the first delete.
- **Two conditions, both required:** the environment is `local`, and the database name matches the expected local one.
- Environment alone is not enough. A local `.env` can point at a production database, and usually the day it does is the day someone is debugging something else.

## Prove the scope of an enumeration before using it destructively

- **Print the output and read it before feeding it into a delete.** A first run of a destructive path is not a test of whether the scope was right.
- `Schema::getTableListing()` without arguments returns every table of **every database on the server**, schema-qualified. A plain-string comparison against a table name silently never matches, and the loop reaches databases that have nothing to do with the project.
- Scope explicitly: take the connection's database name and pass it in — `Schema::getTableListing($database, false)`.

## Never build what the framework deliberately omits

- **There is no `artisan db:truncate-everything`, and that is a decision, not a gap.** When a destructive primitive is missing, the requirement is usually wrong.
- Reach for the built-in that exists — `migrate:fresh`, `db:wipe`, `db:seed`, `RefreshDatabase` — or narrow the requirement.
- The built-ins are destructive too, but their scope is the connection and their behaviour is known to everyone. Hand-written destruction looks tamer and is unbounded by accident.
- **Predictable destruction is manageable; surprising destruction is not.**
