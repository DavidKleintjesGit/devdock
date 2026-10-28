# CLAUDE.md

> This file and everything it imports is read by Claude before starting work. Only add or remove rules when David explicitly asks. Rule files are append-only per section — never remove or rewrite existing rules unless explicitly instructed.

## Rules beat existing code

When a rule conflicts with existing code, the rule wins — existing code that violates a rule should be fixed, always write consistently with existing code unless it violates a rule.

## Rules

Shared rules — portable, travel to every project:

@.claude/rules/architecture.md
@.claude/rules/code.md
@.claude/rules/database.md
@.claude/rules/testing.md

There are no project-only rules right now. A rule that must not travel to another project goes
in `.claude/rules/local/`, imported here alongside the shared ones.
