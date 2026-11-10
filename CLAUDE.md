@AGENTS.md

## Claude Code notes

`AGENTS.md`, imported above, is the single source of truth for this project. Put
project knowledge there, not here, so other agents get it too. This file holds only
Claude Code specifics.

- The Hard rules in `AGENTS.md` override any default behaviour, especially the ban on
  running tests against a real database.
- Prefer the Read tool with `offset` and `limit`, and grep before reading. Do not
  re-read a file after a successful Edit.
- Use the `Explore` subagent only for sweeps across many files that this guide does
  not already answer. Do not spawn subagents for targeted lookups.
- Run `/compact` between unrelated tasks, or `/clear` when starting a new one.
