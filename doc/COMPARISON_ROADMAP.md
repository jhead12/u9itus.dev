# Voter research roadmap

## Phase 1: dedicated public comparison

Implemented at `/compare` using the existing Laravel/Blade/JavaScript application. Visitors search published profiles by state, compare up to three people for a supported seat, share a live URL, and print a source-referenced guide. No login or comparison storage is required.

`GET /api/v1/map/candidate-comparison` accepts optional `context=research`. Default requests retain the existing 90-day election window. Research mode returns current public records year-round, allows a null election, and adds structured state/office/district/city fields to `seat`. The comparison assembly is shared through `CandidateComparisonService`. Existing WebMCP contracts are unchanged. Senate research is unavailable until the data identifies exact Senate seats; the existing map Senate behavior is preserved.

The URL preserves seat context and selected public record keys. Missing selections are identified rather than replaced. Links show live data, not snapshots. The print view includes full statements, numbered sources, generation time, the live URL, and a locally generated QR code. Browser Print / Save as PDF supports portrait Letter and A4, and the saved file is named after the compared people, the seat, and the preparation date. The print-specific booklet follows relevant guidance from [EAC Effective Design, Module 1](https://www.eac.gov/sites/default/files/2026-02/EAC_Effective_Designs_508.pdf): sentence-case question headings, plain-language instructions, consistent left-aligned sans-serif text, prominent election dates, an overview of sections, and one issue per section. Body text is 12 pt, with repeated candidate headers and numbered sources. It is labeled independent voter research, without official seals or a claim of EAC endorsement. Long statements continue onto additional pages instead of being truncated. Page numbers use CSS margin boxes where supported by the browser.

Existing map finance/legislation features are preserved; the dedicated first-release page focuses on party, incumbency, candidacy, and sourced positions. The page does not infer candidate rankings or positions from affiliation, funding, or missing data.

### Validation and rollout

- Run comparison API/page tests, WebMCP regression tests, and JavaScript renderer tests.
- Build frontend assets and run `tests/e2e/comparison.spec.ts` against a local or staging server using `PLAYWRIGHT_BASE_URL`.
- Inspect the generated Letter/A4 PDFs, including long statements and multiple pages.
- Before production release, validate staging with representative real records, then monitor comparison endpoint failures through existing operational logging.
- No database migration or new data provider is required.

## Two experiences, one research base

The public `/compare` page stays simple: search, compare, inspect sources, share, and print without an account. Signed-in voters get a research workspace with an n8n-style node canvas for more advanced comparison. Both read the same public records; the workspace adds saved state, connections, and personal notes.

Evidence rules for every phase:

- Keep documented facts, candidate statements, third-party interpretations, and AI summaries visibly distinct. An AI summary is never labeled as a candidate's statement.
- Missing information stays "Not recorded"; it never counts for or against anyone.
- No inferred positions, rankings, or relationships from party, funding, or missing data.
- Private notes and unfinished connections stay private unless the author explicitly includes them in a published guide.

## Phase 2: signed-in research workspace and candidate canvas

Add an account-owned, private workspace with a node canvas. Nodes are candidates and officeholders from existing public records, articles, and personal notes. The canvas must not require dragging:

- Start from templates: "Compare this race" places a seat's candidates; "Research this issue" places candidates with recorded positions on a topic.
- Add nodes through search (name, district, address, as on `/compare`), arrange them automatically, and switch to an equivalent table view.
- Select candidate nodes to open the same side-by-side comparison as `/compare`, including its print guide.
- Connections carry a label, date, and evidence. Clicking one shows the relationship, source, excerpt, date, and any uncertainty. Personal annotations are styled differently from documented links.
- Save node positions and connections with the workspace. Reuse existing favorites as a source of nodes.

Completion: a signed-in voter can start from a race, build and save a canvas, and generate a sourced comparison from it without dragging.

## Phase 3: richer evidence and organization nodes

Extend both the public chart and the canvas with existing finance and news integrations. Label reporting periods, distinguish contributions from independent spending, keep incompatible periods separate, and preserve missing data. Add PAC, foundation, and ballot-measure nodes, with ballot measures presented separately and their yes/no meanings sourced. Introduce organization and relationship records here rather than forcing them into candidate fields.

Organize reporting around claims and evidence: show what articles agree on and dispute, and recognize several articles repeating one report as a single underlying source. Keep reporting separate from candidate-authored positions. Add topic filters.

Add an Evaluate view: voters choose issues and priorities and see where candidates' recorded positions align, with the reasoning shown and missing or conflicting evidence called out. It is not a ranking.

Completion: readers can trace financial figures, reporting, and organization links to original evidence without treating them as candidate positions.

## Phase 4: published research collections

Turn workspaces into playlist-style collections of comparisons, profiles, ballot measures, articles, and notes. Collections stay private by default; publishing creates a public read-only link. Each collection has three presentations: canvas, an ordered reading view with the author's notes, and a print edition that explains selected connections in text (a large graph is illegible on paper) with source references, dates, and a QR code. Support copying with attribution and dated published editions, so a shared or printed guide stays stable while its author keeps editing. Distinguish author commentary from evidence.

Completion: another person can save or adapt a published guide without changing the original.

## Phase 5: local communities and mobile audio

Connect research to persistent local communities with membership, text discussion, and moderation. Complete mobile access, then add moderated live audio tables, speaker requests, chat, pinned collections, and written recaps. Reporting and blocking precede broad participation.

Completion: a pilot community can research a need, discuss it live, and continue afterward.

## Phase 6: nonprofit participation

Add organization profiles, representative verification, affiliations, connections to documented needs, and local participation opportunities. Donations and fundraising remain outside this release.

Completion: residents can find organizations addressing a local need and understand how to participate.

Later phases require detailed implementation planning when their work begins; this release does not implement their storage, mobile, or community interfaces.
