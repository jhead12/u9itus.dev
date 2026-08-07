# **U9itus Quality Assurance: UX Validation Suite**

This document outlines the user experience (UX) and functional validation questions required to ensure the U9itus platform meets the criteria for the Sprint 8-5 release. These questions are designed to guide a tester or stakeholder through a systematic verification of the platform's current state, focusing on recent Q\&A implementations and financial logic updates.

# **1\. Admin Setting & Propagation Test**

_Goal: To verify that global platform changes correctly influence individual user journeys._

| Test Objective            | UX Validation Question                                                                                                                               | Expected Observation                                                                      |
| :------------------------ | :--------------------------------------------------------------------------------------------------------------------------------------------------- | :---------------------------------------------------------------------------------------- |
| **Global Rate Sync**      | When you change the `viewer_payout_per_view` in the Admin Settings, does the updated amount (e.g., $0.50) appear immediately on the Voter Dashboard? | The Voter Dashboard reflects the new payout value without requiring a manual cache clear. |
| **Minimum Threshold**     | If you set the `min_payout_amount` to $5.00, can a voter with $4.99 in their wallet still see the "Request Payout" button enabled?                   | The button should be disabled or provide a tooltip explaining the requirement.            |
| **Messaging Consistency** | Does the "Voter Payout" confirmation email show the same value as the one currently set in the Admin portal?                                         | Numerical values in system-generated emails must match the current database settings.     |

# **2\. Backward-Compatibility & Campaign Persistence**

_Goal: To ensure that existing campaigns maintain historical integrity (Option A Behavior) while new ones adopt current rules._

- **Question 1:** Select an "Active" campaign created last week. Does it still deduct the historical rate (e.g., $1.00) from the politician's balance, or has it incorrectly jumped to the new global rate?
- **Question 2:** Create a brand-new campaign today. Does the "Post-fee amount" shown during the Stripe top-up phase align with the current transparency logic?
- **Question 3:** View the analytics for an old campaign. Is the platform margin still calculated based on the original $0.35/view logic, or is it broken by the new code?

# **3\. Billing & Invoice Metadata (Stripe Sandbox)**

_Goal: To verify the integrity of financial reporting for politicians._

- **Fee Transparency:** After running a test credit purchase in Stripe, does the invoice clearly break down the `Gross Charge` vs. the `Stripe Fee`?
- **Metadata Verification:** Does the back-end "Analytics" tab for the politician show the specific `fee_percent` applied to their latest transaction?
- **Graceful Fallback:** When you view a transaction from February, which lacks current fee metadata, does the page load correctly with "N/A" or "0", or does it trigger a system error?

# **4\. Q\&A Campaign Workflow Regression**

_Goal: To validate the full lifecycle of the new Q\&A video path._

1. **Draft to Submission:** Can a politician successfully upload a 30-second Q\&A response and see its status change to "Pending Review"?
2. **Approval Logic:** When the Admin approves the Q\&A, does the politician receive an automated email notification?
3. **Voter Watch Path:** As a voter, after watching 10 seconds of a Q\&A video, is the $0.50 credit applied instantly to your balance?
4. **Pricing Parity:** Does the system confirm a $1.00 deduction for the politician for this Q\&A view, matching the standard intro video pricing?

# **5\. Threshold Boundary & Smoke Tests**

_Goal: To stress-test the "edges" of the system and check for known regressions._

## **Boundary Tests**

- **Voter Payout:** Attempt a withdrawal with exactly $5.00 in the wallet. Does the system process it?
- **Near-Failure:** Attempt a withdrawal with $5.01. Does the backend enforcement logic allow the transaction while maintaining a $0.01 remainder?

## **Smoke Tests (Unresolved Items)**

- **Session Behavior:** Perform a logout. Does the screen redirect cleanly to the homepage, or do you encounter the "419 Page Expired" error?
- **Hard-Max Cap:** Attempt to upload a 45-second campaign video. Does the frontend block the upload, or does the backend reject the save with a validation error?

# **6\. User Role, Security & Data-Integrity Tests (Additional)**

_Goal: To ensure user-facing flows are protected against permission leaks, duplicate credits, and stale data conditions._

| Test Objective                           | UX Validation Question                                                                                                                            | Expected Observation                                                                                 |
| :--------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------ | :--------------------------------------------------------------------------------------------------- |
| **Role Isolation (Voter vs Politician)** | If a voter manually visits a politician-only route (for example, campaign upload or campaign billing URL), does the app block access cleanly?     | User is redirected or shown a 403/permission message, never a broken page or exposed controls.       |
| **Admin Surface Protection**             | While logged in as a non-admin account, can you access Admin Settings or moderation pages by URL guesswork?                                       | Access is denied for non-admin users and no admin data is exposed.                                   |
| **Cross-Account Data Leak Check**        | If User A copies a transaction, payout, or campaign detail URL and User B opens it, can User B see User A data?                                   | User B cannot view data owned by another account unless explicitly authorized.                       |
| **Session Expiry Handling**              | After leaving the app idle until session timeout, does the next protected action show a clear re-login path instead of a cryptic error?           | User gets redirected to login with a friendly message and no data corruption occurs.                 |
| **Double-Click / Refresh Idempotency**   | During payout request or video-credit events, if user double-clicks submit or refreshes quickly, is only one transaction created?                 | Exactly one wallet mutation is recorded; duplicates are prevented and user gets consistent feedback. |
| **Video Rewatch Credit Guard**           | If a voter replays the same eligible video repeatedly in a short window, is credit awarded according to business rules (once per eligible event)? | Credits are not repeatedly granted due to replay abuse unless intentionally allowed by policy.       |
| **Concurrent Wallet Update**             | If two payout or earning-triggering actions happen almost simultaneously (two tabs/devices), does wallet balance remain mathematically correct?   | Final balance matches expected ledger totals with no negative drift or double deductions.            |

# **7\. UX Reliability, Communication & Accessibility Tests (Additional)**

_Goal: To validate end-user trust signals: clear status messaging, reliable communications, and usable experiences across devices._

| Test Objective                    | UX Validation Question                                                                                                             | Expected Observation                                                                           |
| :-------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------- |
| **Upload Progress Clarity**       | During large video upload on slower internet, does the user see progress, processing state, and next-step guidance?                | Clear progress/state indicators appear; no ambiguous "stuck" behavior.                         |
| **Failure Recovery Path**         | If upload or payout API fails once (network interruption), can the user retry without creating duplicate records?                  | Error state is clear, retry is available, and backend remains consistent.                      |
| **Notification Timeliness**       | For Q\&A approval/rejection and payout requests, are in-app status updates and email notifications delivered within expected time? | Status and emails are both sent and logically aligned with current record state.               |
| **Notification Content Accuracy** | Do emails and dashboard notifications include correct campaign title, amount, and timestamp for the specific action taken?         | Message details exactly match the triggering transaction/event data.                           |
| **Mobile Usability**              | On a mobile viewport, can users complete key flows (watch, upload, payout request) without clipped buttons or hidden fields?       | Critical actions remain visible, tappable, and fully functional on mobile sizes.               |
| **Accessibility Keyboard Path**   | Can a keyboard-only user navigate to and activate core actions like payout request and campaign submit?                            | Focus order is logical, controls are reachable, and actions can be completed without a mouse.  |
| **Empty-State Guidance**          | For new users with no campaigns or no transactions, are empty screens instructive rather than blank?                               | User sees actionable guidance (for example, "Create your first campaign") and next-step links. |
| **Timezone/Date Consistency**     | Are transaction and payout timestamps displayed consistently between dashboard cards, detail pages, and emails?                    | Timestamps do not conflict across UI surfaces for the same event.                              |

# **8\. Latest Sprint Regression Additions (Sprint 1 \+ Sprint 2)**

_Goal: To validate recently shipped sprint items and prevent immediate post-release regression on newly delivered functionality._

| Sprint Item                                             | UX Validation Question                                                                                                                                           | Expected Observation                                                                                       |
| :------------------------------------------------------ | :--------------------------------------------------------------------------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------------------- |
| **Sprint 1: Admin Logout CSRF Safeguard**               | As an admin, if you submit logout with an expired/stale session token, do you get a clean redirect instead of a 419 error page?                                  | Session is invalidated safely and user is redirected to login/home without a raw 419 error screen.         |
| **Sprint 1: Campaign Video Duration Enforcement**       | During campaign create/edit, does the UI enforce 30 to 300 seconds, and does backend validation reject values outside this range?                                | Values below 30 or above 300 are rejected with clear validation messaging; in-range values save correctly. |
| **Sprint 1: Notification Bell Hydration**               | After receiving a new notification, does the dashboard bell count update correctly and match unread notification list totals?                                    | Bell indicator, unread count API, and notification list remain synchronized.                               |
| **Sprint 1: Mark-One / Mark-All Notification Behavior** | When marking one notification read and then marking all read, do counts and item states update instantly and stay correct after refresh?                         | Read state persists and unread totals remain accurate across page reloads.                                 |
| **Sprint 1: Dynamic Amount Propagation Coverage**       | After changing payout or pricing platform settings, do campaign forms, payout screens, notifications, and user-facing amount labels all reflect the same values? | All amount surfaces show consistent updated values with no stale pricing fragments.                        |
| **Sprint 2: Guest Directory Browsing (View-Only)**      | As a logged-out visitor, can you browse politician directory and profile pages, but get prompted to log in before watch-and-earn actions?                        | Guest browsing works for public pages; protected earning/watch actions require authentication.             |
| **Sprint 2: District Lookup by Address**                | When entering valid, invalid, and partial addresses, does district lookup return accurate district assignment or clear fallback messaging?                       | Valid input resolves correctly; invalid/partial input fails gracefully with actionable message.            |
| **Sprint 2: Home Browse Entry for Guests**              | From homepage as a guest, do campaign browsing entry points load correctly and route to publicly accessible content?                                             | Guest entry links work, with no auth loop or broken navigation.                                            |
| **Sprint 2: Candidate Matching Review/Admin Approval**  | After import/review flow actions, are candidate statuses (pending/approved/rejected) reflected correctly in admin lists and detail views?                        | State transitions are accurate, auditable, and visible immediately in admin UI.                            |
| **Sprint 2: California Import Data Enrichment**         | For newly imported CA unclaimed profiles, do city, contact metadata/bio details, and seeded video links appear correctly on public-facing pages?                 | Imported profile fields are populated as expected and render correctly in public profile views.            |

# **9\. Town Hall Feature Tests (Q\&A Experience)**

_Goal: To validate the Virtual Town Hall experience end-to-end, including discovery, watch flow, and engagement prompts._

| Test Objective                          | UX Validation Question                                                                                                                               | Expected Observation                                                                                   |
| :-------------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------------------------------------------------------- |
| **District-Scoped Q\&A Discovery**      | As a voter, when you open Town Hall/Q\&A browse, do you only see relevant content for your district (or a clearly labeled out-of-district state)?    | Results are district-appropriate, or out-of-district content is clearly identified and not misleading. |
| **Topic Filter Accuracy**               | When switching topic filters (for example Economy, Education, Public Safety), do results update correctly without stale cards from previous filters? | Filtered list reflects selected topic accurately and resets pagination/counts as expected.             |
| **Search \+ Filter Combination**        | If you search by politician name and apply a topic filter together, do both constraints apply correctly?                                             | Results satisfy both search and topic conditions with no unrelated items.                              |
| **Profile Composition (Intro \+ Q\&A)** | On public politician profiles, are intro videos and Q\&A responses presented in a clear combined layout with correct labels?                         | Intro and Q\&A sections are distinct, understandable, and consistently ordered.                        |
| **Q\&A Status Visibility**              | For politicians, do draft/pending/approved/rejected Q\&A entries show clear status badges and next actions?                                          | Status indicators are accurate and users can understand what action is required.                       |
| **Playback Gating for Earnings**        | During Town Hall video playback, is watch-credit eligibility granted only after required watch duration (for example 10 seconds) is met?             | No credit before threshold; credit event occurs once threshold is reached.                             |
| **Pause/Seek Abuse Protection**         | If a voter scrubs forward, repeatedly seeks, or rapidly toggles play/pause, does the system prevent false completion/credit events?                  | Completion and credit logic resists player-manipulation edge cases.                                    |
| **Replay Behavior**                     | After earning once from an eligible Q\&A watch, does replay behavior follow policy (no duplicate credit unless explicitly allowed)?                  | Duplicate credits are blocked unless business rules permit repeat rewards.                             |
| **Post-View Engagement Prompt**         | After completing a Town Hall watch, does the engagement prompt/feedback step appear reliably and save user input correctly?                          | Prompt appears at the right time, submission succeeds, and response persists.                          |
| **Engagement Prompt Failure Handling**  | If engagement prompt submission fails (network/API), does the UI show retry guidance without losing user input?                                      | User sees clear error state, can retry, and typed response is preserved when possible.                 |
| **Media Source Compatibility**          | For hosted and linked media sources, do Town Hall videos load, play, and report completion consistently across supported formats?                    | Playback works across supported providers with consistent completion analytics.                        |
| **Cross-Device Continuity**             | If a user starts Town Hall viewing on desktop and continues on mobile, does watch/progress state remain coherent?                                    | No contradictory progress state; user can continue without duplicated rewards.                         |
| **Accessibility: Captions \+ Controls** | Are captions/transcripts (if provided) and core player controls usable by keyboard and screen-reader users?                                          | Accessibility paths are functional for essential viewing and interaction actions.                      |
| **Moderation Safety Net**               | If a Q\&A is rejected by admin after initial submission, does it disappear from voter-facing Town Hall listings immediately?                         | Rejected/unapproved items are not visible in public voter browse views.                                |

---

**Lead Tester:** Person  
**Validation Date:** Date  
**QA Document Reference:** File
