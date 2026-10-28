# DevDock

Claude Code works one project at a time. Your work does not: it is spread over tickets, pull
requests, checkouts on disk and the sessions you opened on them.

DevDock runs locally and puts two things in one place — **the work**, as a board, and **the setup
Claude runs under**, as the rules, skills and sessions of every project you have.

## The work

Everything is tied together by ticket key. A ticket and a pull request that both say `ACME-482`
are **one card**, with the sessions that worked on that branch attached. No ticket key? The pull
request is the task. No pull request either? The local branch is.

Five columns, ordered by who is holding things up:

| Column | Waiting on |
| --- | --- |
| To do | you — nothing started yet |
| Working on it | a colleague — open, nobody approved it |
| Changes requested | you — a reviewer wants something |
| Ready to merge | you — approved, nothing in the way |
| Done | nobody — merged recently |

Three buttons cut through it: **Needs you**, **High priority** and **Stalled**. DevDock never
merges, closes or comments. It reads, and it starts Claude Code for you.

**From a card to a session.** Write a prompt on a card and Claude Code runs in that project's
folder on that branch, as a new session or continuing one that is already attached.

**From a review to a prompt.** When a reviewer requests changes, the remarks that are still open
become a prompt in one click — each with its file and line, and a way of working that tells the
agent to check whether the problem is still there, and to change nothing outside the code without
asking first.

## The setup

Every checkout carries its own instructions, and every machine collects its own skills and
sessions. That spreads out fast, and nothing tells you when one project drifts from the rest.

- **Rules** — the instruction files of every checkout, side by side with the shared set they came
  from. See which project lags behind, pull the shared version over it, or copy a file into
  another project.
- **Skills** — every skill on the machine: global, per project, and from installed plugins. Create
  one, or copy it where you need it.
- **Sessions** — every Claude Code conversation on disk, with the ones asking to be cleaned up a
  click away: abandoned, oversized, unnamed, or named after the wrong ticket. Rename or delete
  them here.

Workflows — running a set prompt across several projects at once — is the next piece.

## Getting it running

Needs PHP 8.4, Node and an Atlassian account.

```bash
composer install
npm install && npm run build
php artisan migrate
php artisan devdock:fetch
```

The keys go in `.env`, or in Settings, which writes the same file: your Atlassian account and a
token for Jira (`read:jira-work`, `read:jira-user`) and for Bitbucket (`read:repository`,
`read:pullrequest`), your display name so only your own pull requests show, and the paths to your
checkouts, your sessions folder and your shared rules.

A source that cannot be reached is reported and skipped; it never wipes what was already there.

## How it is built

Laravel 13, Livewire 4, SQLite, no CSS framework. One folder per domain under `src/`, each with
its own actions, contracts, repositories and objects. Controller → action → repository, nothing
skipped: Eloquent stays inside a repository and the rest of the application sees typed entities.
