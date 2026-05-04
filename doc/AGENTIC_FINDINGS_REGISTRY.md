# Agentic Findings Registry

Purpose: Keep durable, linkable findings in a format that both humans and agents can consume.

## Files

- Canonical machine-readable file: doc/AGENTIC_FINDINGS_REGISTRY.json
- This guide: doc/AGENTIC_FINDINGS_REGISTRY.md

## Update Rules For Agents

1. Add new findings only if they are evidence-backed.
2. Keep finding IDs stable once created.
3. Never delete old findings; set status to resolved or superseded.
4. Always include at least one evidence link with a line reference.
5. Keep language concise and action-oriented.

## Severity Scale

- critical: likely security, financial integrity, or data-loss impact
- high: major correctness or cross-surface drift risk
- medium: architectural debt with concrete regression risk
- low: clarity, operability, or maintainability issue

## Status Scale

- open
- in_progress
- resolved
- superseded

## Evidence Link Format

Use workspace-relative markdown links with line anchors:

- [app/Http/Controllers/Api/AdminController.php#L74](app/Http/Controllers/Api/AdminController.php#L74)

## Minimal Finding Shape

- id
- title
- severity
- status
- summary
- evidence: list of path, line, link
- plan_phase
- owner
- updated_at

## Current Finding IDs

Synced from doc/AGENTIC_FINDINGS_REGISTRY.json on 2026-05-03.

- PATT-001: API admin analytics mixes mode-scoped and global financial aggregates
- PATT-002: Billing refund math still relies on float and round in several paths
- PATT-003: Payment mode helper logic is duplicated across controllers
- PATT-004: Admin moderation and payout workflows are duplicated across API and standalone controllers
- PATT-005: Stripe warning references a non-canonical env key name
- Next available ID: PATT-006

## Suggested Agent Workflow

1. Read doc/AGENTIC_FINDINGS_REGISTRY.json
2. Add new findings with next ID number
3. Update status on existing findings if work is completed
4. Keep evidence links current after refactors
