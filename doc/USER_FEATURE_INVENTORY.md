# U9itus user feature inventory

Reviewed: September 24, 2026. Mobile status and planned features added: September 25, 2026. Ballot-measure committee funding added: September 25, 2026.

This inventory describes the current web application's user-facing features by audience, based on routes, controllers, access rules, Blade screens, and browser code in this repository. It is a source review, not a production browser acceptance test. Related actions are grouped into features rather than listing every API endpoint separately. Legacy code, background jobs, and diagnostic endpoints are excluded. The mobile app and future features are covered separately in [Mobile app status](#mobile-app-status) and [Planned features](#planned-features-not-yet-implemented); nothing in those sections is available to users today.

Locations are application-relative URLs or named areas within a screen. Replace `{slug}`, `{campaign}`, `{event}`, and other placeholders with the relevant record identifier. Unless a row says otherwise, the feature has a route and implementation in source; availability can still depend on published content, permissions, account state, and configured services. Known gaps are listed at the end.

## Audience and access overview

| Audience | Experience | Main entry point |
| --- | --- | --- |
| Public visitor | Research candidates, officials, districts, campaign funding, news, and community activity without a full account. | `/`, `/map`, `/politicians` |
| Voter | Research and follow civic interests, watch eligible campaigns, receive earnings, and refer others. | `/voter/dashboard` |
| Citizen | Sponsor local business/community messages, publish posts, host events, and manage a business presence. This is a distinct account role, not a synonym for every site visitor. | `/citizen/dashboard` |
| Politician | Maintain a public profile, fund and manage campaigns, respond to voter questions, publish posts, and host events. | `/politician/dashboard` |
| Admin | Moderate content, manage users and civic data, oversee money and fraud, and configure the platform. Staff capabilities depend on assigned permissions. | `/admin/dashboard` |
| Approved contributor | Submit sourced political chatter for editorial review. This capability is independent of the four main portal roles. | `/contribute/chatter` |

An account can hold both Voter and Citizen roles and switch through `/portal-pick`. Role portals generally require authentication, verified email, the appropriate role, applicable onboarding, and a two-factor challenge when enabled. Public reading does not confer permission to use paid viewing or editing features.

## Public-facing features

### Discovery, maps, and research

| Feature | Short description | Location |
| --- | --- | --- |
| Homepage | Introduces the platform, highlights candidates and recent news, and provides paths into research and registration. Featured candidates can be localized when location lookup is configured. | `/` |
| Follow the Money highlights | Surfaces prominent committees/PACs with links into funding research. Requires populated committee profiles. | Homepage, Follow the Money area |
| Ballot measure spotlight | Features one upcoming measure, local first (the visitor's state preferred, then one with committees on both sides): what a YES and a NO vote mean, the verified committees pushing each way, who funds them, and amounts as secondary detail, with a link to the measure's funding page. Falls back to a statewide measure; hidden when no measure has a verified committee. | Homepage, Follow the Money area |
| Politician and government directory | Browse published profiles; search and narrow results by geography, office/governance context, party, and issues, with sorting options. | `/politicians` |
| District lookup | Enter a location to identify districts, associated candidates and current officials, with election/voting information when supplied by connected data sources. | `/district-lookup` |
| Interactive U.S. map | Explore states and districts through a zoomable, tiltable map with national/state navigation, breadcrumbs, and reset controls. | `/map` |
| Map search and location | Search candidates, places, and businesses; use address entry or device location to navigate to an area. | `/map`, search/address/location controls |
| Map layers | Toggle district boundaries, party, population, cities, top cities, candidates, posts/content, and businesses. Coverage varies with loaded data. | `/map`, layer controls |
| Regional and district information | Inspect region/district panels, demographics, census information, officials, races, candidate news, and local content. | `/map`, selected region or district panel |
| Candidate detail drawer | Open a candidate from the map to inspect overview, news, funding/economic context, media moments, and links to the full profile. | `/map`, candidate marker or search result |
| Local business discovery | Find opted-in citizen businesses and inspect map business entries. | `/map`, business layer/search |
| Guided map help | Take the map tour and use keyboard help, shortcuts, view/depth controls, and mobile navigation. | `/map`, help and controls |
| Saved map areas | Save districts/cities for later. Guests use cookie-backed favorites; voters can use account-backed saved boundaries. | `/map`, boundary favorite control |
| Area email digest | Opt in to updates about saved boundaries, confirm by emailed link, and unsubscribe through a signed link. | `/map`, digest opt-in; `/map/boundaries-digest/confirm/{voter}/{hash}` and `/map/boundaries-digest/unsubscribe/{voter}/{hash}` |
| Candidate comparison | Select candidates to compare across available profile/research fields; use race selection and map comparison tools. | `/compare`; `/map`, comparison panel |
| Save/share comparisons | Save map comparisons locally and share comparison URLs. | `/map`, saved comparisons; `/compare`, share control |
| Printable voter guide | Print the selected comparison or save it through the browser's PDF workflow, including a QR link back to the comparison. | `/compare`, print control |
| Office glossary | Explain offices and geographic/government terminology used in comparisons. | `/compare/glossary` |
| PAC/committee directory | Search and browse committee funding profiles. | `/pacs` |
| PAC/committee profile | Inspect a committee's available financial summary and associated information/source links. | `/pacs/{committee}` |
| Report a data problem | Submit a correction report from research surfaces for admin review. | Map data-report controls and feedback widget; submission to `/api/v1/data-reports` |

### Public politician profiles

These sections are conditional: a profile must be public, and individual sections may require data, politician settings, or enabled integrations.

| Feature | Short description | Location |
| --- | --- | --- |
| Identity and biography | View name, photo, party, office, geography, biography, and available verification/issue badges. | `/p/{slug}`, overview and About |
| Policy platform | Read published initiatives and policy positions. | `/p/{slug}`, Platform & Policy Positions |
| Campaign previews and archive | Browse running and past campaign videos/updates. Public preview viewing is separate from the authenticated earning flow. | `/p/{slug}`, Campaign Videos & Updates |
| Town hall answers | Read publicly eligible answered voter questions. | `/p/{slug}`, question/answer area |
| Posts and events | Discover the politician's published writing and civic events when available. | `/p/{slug}`, posts/events areas; linked `/blog/{slug}` and `/events/{event}` |
| Videos and appearances | Watch or open linked video appearances and featured media moments. | `/p/{slug}#profile-videos` |
| Favorite songs | Play politician-selected Spotify, Apple Music, or YouTube embeds and read accompanying notes. | `/p/{slug}`, Favorite Songs |
| Connect links | Open available official website and social links. | `/p/{slug}`, Connect |
| Public chatter | Read editorially approved, sourced chatter with its claim/moderation context. | `/p/{slug}#profile-chatter` |
| Candidate news | Read news summaries and follow source articles; browse the dedicated news page. | `/p/{slug}#profile-news`; `/p/{slug}/news` |
| Funding and donors | Inspect available donors, industries, fundraising summaries, outside spending, and PAC affiliations, with committee/source links. | `/p/{slug}#profile-money` |
| Transparency research | Dig into available Ballotpedia, OpenSecrets, Vote Smart, and FEC information and source links. | `/p/{slug}#profile-sources` |
| Congressional record | Inspect available voting records, committee assignments, and legislative context for covered officials. | `/p/{slug}`, congressional sections; `/p/{slug}/votes` |
| Floor speeches | Browse available congressional floor speech records and linked media/source material. | `/p/{slug}/speeches`; profile speech section |
| Loyalty-token information | Display configured on-chain loyalty-token information and external links. Conditional integration; not a general-purpose in-app trading feature. | `/p/{slug}`, On-Chain Loyalty Token panel |
| Profile sharing and following | Share a profile; eligible voters can follow it and use associated personal research actions. | `/p/{slug}`, sharing/favorite controls |
| Claim an existing profile | Submit a profile ownership claim and verify through an emailed token. | `/p/{slug}/claim`; `/p/{slug}/claim/verify` |

### Community, information, and entry flows

| Feature | Short description | Location |
| --- | --- | --- |
| Public blog | Browse published posts, read individual articles, and explore topic, author, and feed views. | `/blog`, `/blog/{slug}`, `/blog/topic/{slug}`, `/blog/author/{type}/{slug}`, `/blog/feed` |
| Event discovery | Search upcoming published civic events by text, location, and topic. | `/events` |
| Event details and calendar export | See event information and download an ICS calendar entry. | `/events/{event}`; `/events/{event}/ics` |
| Event RSVP | Submit an attendance response from an event page; authentication and verified email are required. | `/events/{event}`, RSVP card |
| Neighborhood group directory | Discover community groups and view public group information/scoped content. | `/groups`; `/groups/{group}/{scope?}` |
| Earnings explainer | Learn how paid campaign viewing works and proceed to voter registration or the waitlist. | `/earn` |
| About and policies | Read platform background, terms, and privacy information. | `/about`, `/terms`, `/privacy-policy` |
| Role selection and registration | Choose Voter, Citizen, or Politician and create an account. | `/register`; `/register/voter`, `/register/citizen`, `/register/politician` |
| Registration waitlist | Join the mailing list when registration is closed. | `/register/closed` |
| Sign-in and password recovery | Log in, request a reset link, and set a new password. | `/login`, `/forgot-password`, `/reset-password/{token}` |
| Civic AI tool console | Use the WebMCP demonstration/catalog for candidate search and dossiers, comparisons, news, ballot measures, and elections; submit candidate leads or ballot watch requests through its tools. | `/webmcp`; backing `/api/v1/mcp/*` endpoints |

## Shared account features

| Feature | Short description | Location / access |
| --- | --- | --- |
| Account settings | Edit basic account information and access account deletion through the shared profile screen. | `/profile`, authenticated accounts |
| Email verification | Verify an email address and resend the verification message. | `/email/verify` |
| Phone verification | Enter a phone verification code and request another code. | `/verify-phone` |
| Two-factor authentication | Set up an authenticator, complete challenges, disable enrollment, and rotate recovery codes. Non-admin roles also have SMS recovery using an eligible verified phone. | `/2fa/setup`, `/2fa/challenge`, `/2fa/recovery`; admins use `/admin/security/2fa` and `/admin/2fa/challenge` |
| Identity verification | Begin an ID.me flow and inspect verification status when configured; role screens also expose applicable document/Stripe verification workflows. | `/verification/idme/redirect`, `/verification/idme/status`; role profile/earnings areas |
| Notification center | Inspect notifications and unread counts, mark items/all as read, and remove notifications. Real-time delivery depends on deployment configuration. | Portal notification bell/center |
| Notification preferences | Choose supported notification channels and register web push delivery where available. | `/notification-preferences` |
| Role-aware navigation | Enter the correct dashboard after login; dual Voter/Citizen accounts can choose a portal. | `/dashboard`, `/portal-pick` |
| Source contribution | Approved contributors submit a public source, candidate association, headline, notes, and optional excerpt; inspect their submission status. Submissions await editorial review. | `/contribute/chatter` |
| Browser clipping | Use the contributor extension setup and clip handoff to capture source material. Actual submission requires contributor access. | `/contribute/chatter/extension`, `/contribute/chatter/clip`; `browser-extension/chatter-clipper/` |

## Voter profile and portal

| Feature | Short description | Location |
| --- | --- | --- |
| Guided onboarding | Welcome, profile setup, first watch, payout setup, referrals, and web-reporter introduction, with supported skip/progress handling. | `/voter/onboarding/welcome` and related onboarding steps |
| Dashboard | View the voter overview, account/earning context, candidate news, and links to research and viewing tools. | `/voter/dashboard` |
| Ad Viewing Room | Browse available campaigns and select an eligible campaign to generate a watch token. | `/voter/ad-room` |
| Secure political campaign viewing | Open a tokenized viewing session, track watch progress, and complete eligible views. Supported player branches include YouTube, Vimeo, direct video/S3, and HLS. | `/voter/watch/{token}` |
| Post-view survey | Submit campaign engagement/survey responses after viewing. | Watch completion flow |
| Town hall participation | Ask a campaign question and browse watch-related questions/answers. | Watch screen; `/voter/watch/{token}/questions` |
| Message politician | Send a message associated with the watched campaign. This is a campaign interaction, not evidence of a general chat inbox. | `/voter/watch/{token}`, message action |
| Report viewing issues | Report problems with political or citizen campaign playback/content. | Relevant watch screen |
| Community campaign viewing | Watch eligible Citizen campaigns, complete a view, and ask the sponsor a question. | `/voter/citizen-campaigns/{campaign}/watch` |
| Earnings balance and payout request | Inspect earnings and request an eligible payout. Eligibility, holds, thresholds, and verification affect availability. | `/voter/earnings` |
| Earnings history | Review historical earning/payment activity. | `/voter/earnings/history` |
| Authentic User Verifier and wallet | Begin Stripe-hosted account verification/onboarding and open the connected wallet dashboard when eligible. | Earnings/verifier controls; POST actions `/voter/authentic-user-verifier/start` and `/voter/wallet/manage` |
| Payout preferences | Save displayed PayPal or Cash App payout details. A saved preference alone does not establish automated support for that provider. | `/voter/preferences` |
| Referrals | Obtain/share voter and politician referral links and inspect referral activity/earnings according to platform rules. | `/voter/referrals` |
| Early-bank connection | Open the connected Early-bank experience through single sign-on where configured. | Referral/Early-bank CTA; `/voter/earlybank/sso` |
| Personal profile | Maintain voter information/location, change password, upload/view identity documents, and access security settings. | `/voter/profile` |
| Issue badges | Add/remove interest badges and control their visibility. | Voter profile badge controls |
| Followed politicians | Follow/unfollow politicians and return to a saved list/panel. | `/voter/favorites`; directory/profile/map favorite controls |
| Saved news | Bookmark articles and read the saved list. | Article heart/save actions; `/voter/favorites#saved-articles` |
| Saved geographic areas | Maintain account-backed district/city boundaries for map research. | Map saved-boundary controls; backing `/voter/boundaries` endpoints |
| Causes | Browse causes, read details, and favorite/unfavorite causes. | `/voter/causes`, `/voter/causes/{cause}` |
| Ballot measures | Browse measures, inspect detail/yes-no context where populated, and save measures of interest. | `/voter/ballot-measures`, `/voter/ballot-measures/{measure}` |
| Ballot-measure funding ("Who's Funding This") | See the campaign committees supporting and opposing a measure, each with its state filer ID and a link to the filing, plus a "View official filings" link to the state's campaign finance site. For California, Florida and Texas, each side shows calendar-year totals (raised, non-cash, spent, and cash on hand where the state publishes it) plus late contributions reported since the latest statement, with money passed between committees on the same side counted once, and the side's top 10 donors (with employers where filed). A decided measure shows its election year's totals. Only admin-verified committee links are shown. | "Who's Funding This" section of `/voter/ballot-measures/{measure}` |
| Private politician notes | Maintain a running personal note for a politician. | Personal note control; backing `/voter/politicians/{politicianId}/note` endpoints |
| Embedded research map | Use the map within the voter portal. | `/voter/map` |
| Study Systems | Browse categorized external learning, research, productivity, and other utility links. | `/voter/study-systems` |
| Add Citizen profile | Add Citizen capabilities to an existing voter account and access the dual-role portal picker. | `/voter/add-citizen-profile` |
| Neighborhood participation | Create/join/leave groups; authorized group managers can edit the group, manage members/roles, and create/edit/cancel group events. | `/groups/create`, `/groups/{group}/edit`, `/groups/{group}/members`, `/groups/{group}/events` |

Guest-trial mode can provision a temporary voter session on eligible voter paths when enabled. It does not provide real earnings; money-related pages/actions are separately blocked. Full registration upgrades the trial experience. Causes and ballot-measure web screens are voter routes, even if trial mode makes them appear accessible during guest browsing.

## Citizen profile and portal

| Feature | Short description | Location |
| --- | --- | --- |
| Dashboard | Review the citizen account, campaign activity, credit context, and publishing tools. | `/citizen/dashboard` |
| Campaign creation | Create Local Business, Community Notice, Ballot Issue, or General Announcement campaigns with media, a call to action, budget, and ZIP/radius targeting. | `/citizen/campaigns/create` |
| Campaign management | List, inspect, edit, and delete campaigns as their lifecycle permits. | `/citizen/campaigns`, `/citizen/campaigns/{campaign}`, `/citizen/campaigns/{campaign}/edit` |
| Campaign review and submission | Preview/review a campaign and submit it for review. Ballot-issue campaigns require PAC registration information and admin review; other approval behavior depends on verification/policy. | `/citizen/campaigns/{campaign}/review`; submit-review action |
| Video uploads | Upload video or use the S3 upload/processing workflow when configured. | Campaign create/edit screens |
| Campaign results | Inspect campaign details, completed/requested views, budget, and spending. | `/citizen/campaigns/{campaign}` |
| Viewer questions by email | Receive voter-submitted campaign questions through the sponsor email delivery flow when mail is configured. | Sponsor email inbox; submitted from the voter watch screen |
| Credit purchases | Add funds through the configured Stripe billing flow and view available credits. | `/citizen/billing` |
| Payment methods | Save or remove supported payment methods. | `/citizen/billing` |
| Invoices and receipts | Browse transactions/invoice details, set a receipt email, and resend receipts. | `/citizen/billing/invoices`; billing screen |
| Business profile and map visibility | Set business name/category/address and opt into appearing on the public map. | `/citizen/settings` |
| Interest badges | Add/remove topic interests used by personalized workspace content. | Citizen workspace topic/badge widget |
| Personal workspace | Add/remove/rearrange widgets for campaign overview, activity, local news, voting updates, and topic badges. | `/citizen/workspace` |
| Blog publishing | Draft, edit, preview, publish/submit, archive, and delete posts; add images/embeds and promote posts through the supported campaign flow. Publication remains subject to policy/moderation. | `/citizen/posts`, `/citizen/posts/create`, `/citizen/posts/{post}/edit` |
| Civic event hosting | Create/edit/cancel events and inspect, approve, or decline RSVPs. | `/citizen/events`, `/citizen/events/create`, `/citizen/events/{event}/rsvps` |
| Neighborhood groups | Create/join/leave groups; group permissions govern editing, member management, and group event management. | Shared `/groups/*` management screens |
| Account and security | Use shared account, email/phone verification, notification, and two-factor settings. | `/profile`, `/notification-preferences`, `/2fa/setup` |

There is no dedicated `/citizen/profile` page or required Citizen onboarding wizard in the current route set. Basic account details use `/profile`; business details use `/citizen/settings`.

## Politician profile and portal

| Feature | Short description | Location |
| --- | --- | --- |
| Guided onboarding | Set up the political profile, payment method, first campaign, and credits after the welcome step. | `/politician/onboarding/welcome` and related steps |
| Dashboard | Review campaign/credit/activity summaries and navigate to management tools. | `/politician/dashboard` |
| Political identity | Edit name, office, party, biography, website, governance level, district, state/city, social links, and video appearances. | `/politician/profile` |
| Verification documents and security | Upload/view KYC documents and access two-factor settings. | `/politician/profile` |
| Public page design | Configure public-page appearance, section visibility, and publishing settings. | `/politician/public-page` |
| Platform initiatives | Create, edit, order/configure, and remove policy initiatives displayed on the public page. | Public-page management; `/politician/initiatives` actions |
| Topic badges | Add/remove self-declared issue badges. | Politician badge controls; `/politician/badges/{topicId}` actions |
| Favorite songs | Add/remove/reorder streaming-service picks for the public profile. | `/politician/song-picks` |
| Transparency preferences | Configure external transparency identifiers/opt-ins and initiate government-email verification. | `/politician/transparency-settings`; email link `/politician/verify/{token}` |
| Loyalty-token fields | Maintain wallet/token contract information when the feature is enabled. | `/politician/profile`, wallet/MeToken fields |
| Campaign creation and drafts | Build campaign video/live/Q&A messages with targeting, media, budget, and available settings; save drafts. | `/politician/campaigns/create` |
| Campaign lifecycle | List, view, edit, delete, submit for review, pause, or resume campaigns where allowed. | `/politician/campaigns`, `/politician/campaigns/{campaign}` and edit screen |
| Video upload and processing | Upload campaign media, including configured large-file S3 processing. | Campaign create/edit screens |
| Voter questions and replies | Read campaign questions and submit replies that can feed the public Q&A experience after applicable moderation. | `/politician/campaigns/{campaign}/questions`; campaign detail |
| Performance analytics | Inspect overall and per-campaign performance reports. | `/politician/analytics`, `/politician/analytics/{campaign}` |
| Billing and credits | Purchase campaign credits, review balance, and save/remove payment methods. | `/politician/billing` |
| Invoices and receipts | Review transaction details, change receipt email, and resend receipts. | `/politician/billing/invoices`; billing screen |
| Referrals | Access politician referral information and sharing tools. | `/politician/referrals` |
| Blog publishing | Create/edit/preview/publish/archive/delete posts, include images/embeds, and promote posts through the supported campaign workflow. | `/politician/posts`, `/politician/posts/create`, `/politician/posts/{post}/edit` |
| Civic event hosting | Create/edit/cancel events and manage RSVP approvals/declines. | `/politician/events`, `/politician/events/create`, `/politician/events/{event}/rsvps` |
| Embedded research map | Explore the civic map inside the politician portal. | `/politician/map` |

Politicians can browse public neighborhood groups, but the shared group creation/membership routes are limited to Voter or Citizen roles.

## Admin profile and portal

Admin access is permission-scoped. The owner manages staff access; ordinary staff see only the operations their assigned capabilities allow. The tables describe the collective admin feature set, not a promise that every staff account can use every action.

| Feature | Short description | Location |
| --- | --- | --- |
| Admin sign-in and onboarding | Use a dedicated login and guided introduction to campaign approval, fraud, and payouts. | `/admin/login`; `/admin/onboarding/welcome` and related steps |
| Operational dashboard | Review platform activity and available work queues; staff may receive a permission-appropriate home screen. | `/admin/dashboard` |
| Custom workspace | Add/remove/rearrange statistics, pending queues, local news, voting updates, and workflow-health widgets. | `/admin/workspace` |
| Staff access management | Owner-only role/capability assignment, custom staff-role management, contributor access, and contributor-request handling. | `/admin/staff-access` |
| Campaign review | Inspect pending political/citizen campaigns and approve/reject them, including available bulk operations. | `/admin/campaigns/pending` |
| Running campaign control | Inspect running campaigns and stop/reactivate political campaigns; pause/stop/reactivate citizen campaigns. | `/admin/campaigns/running` |
| Campaign editing | Correct political campaign settings/content through the admin editor. | `/admin/campaigns/{campaign}/edit` |
| Campaign audit history | Inspect campaign lifecycle changes and recorded reasons. | `/admin/campaigns/{campaign}/audit` |
| User management | Browse user details, suspend/unsuspend/delete accounts, and apply supported bulk actions. | `/admin/users`, `/admin/users/{user}` |
| Deleted-account recovery | Inspect deleted-account records and restore eligible accounts. | `/admin/deleted-accounts` |
| Candidate matching | Review candidate matches, import election candidates, retry matching, and approve/reject individual or bulk reviews. | `/admin/candidate-matches` |
| Import operations | Inspect import runs, health/counts/errors, seed unverified profiles, and import OCR-derived candidates. | `/admin/imports` |
| Data-quality review | Approve/reject cleanup findings individually or in bulk. | `/admin/data-quality` |
| Visitor correction reports | Triage/update reports submitted from public research screens. | `/admin/data-reports` |
| Chatter editorial review | Add/edit sourced chatter and perform permission-appropriate moderation before public display. | `/admin/politician-chatter` |
| Fraud dashboard | Inspect fraud context and flagged voters, and clear voter flags when authorized. | `/admin/fraud` |
| Flagged-view review | Inspect and review suspicious viewing activity. | `/admin/fraud/flagged-views` |
| Registration abuse controls | Inspect registration attempts/IP blocks and block/unblock IPs. These are API-backed admin capabilities, not a separate confirmed web page. | `/api/v1/admin/registration-security/*` |
| Identity/KYC review | View permitted identity documents and approve/reject verification. | `/admin/kyc` |
| Payout operations | Review payouts/pending balances and process a payout batch. | `/admin/payouts`, `/admin/payouts/pending` |
| Skipped payouts | Review skipped items and force-pay supported below-minimum cases when authorized. | `/admin/payouts/skipped` |
| Politician credit refunds | Review transactions and refund eligible unused politician credits. | `/admin/billing/refunds` |
| Citizen credit refunds | Review transactions and refund eligible unused citizen credits. | `/admin/citizen-billing/refunds` |
| Platform analytics | Inspect platform reports and campaign/voter accounting ledgers. | `/admin/analytics`, `/admin/analytics/ledger/campaign`, `/admin/analytics/ledger/voter` |
| Accounting exports | Export campaign and voter accounting datasets. | Analytics export controls; `/admin/analytics/export/campaign-accounting`, `/admin/analytics/export/voter-accounting` |
| Revenue reporting | Review platform revenue reporting. | `/admin/reports/revenue` |
| Engagement and question moderation | Inspect survey/question trends and moderate questions. | `/admin/reports/engagement` |
| District-search reporting | Review district lookup activity and export it. | `/admin/district-searches`; `/admin/district-searches/export` |
| General/security settings | Maintain platform/admin settings, security policy, account password, and send a test email. | `/admin/settings` |
| Pricing and platform controls | Edit dynamic platform settings such as pricing/commissions and registration behavior; configure guest trials and clear settings cache. | `/admin/platform-settings` |
| Office education profiles | Maintain civic office information used in voter-facing popups and mark records verified/unverified. | `/admin/office-profiles`, `/admin/office-profiles/{politician}/edit` |
| Email templates | Edit, preview, enable, or disable notification email templates. | `/admin/email-templates` |
| Blog authoring/moderation | Create/edit posts and approve, unpublish, archive, restore, delete, or bulk-manage posts according to capabilities. | `/admin/posts`, `/admin/posts/create` |
| Topic taxonomy | Create/edit/delete issue topics used across campaigns, causes, and badges. | `/admin/topics` |
| Causes management | Create/edit/delete causes that voters can browse and save. | `/admin/causes` |
| Ballot-measure management | Create/edit/delete measures; preview and store imported measure data. | `/admin/ballot-measures`, `/admin/ballot-measures/import` |
| Committee suggestions from filings | For California, Florida and Texas, see committees that a linked committee funded for the measure (from late contribution reports), with the amount and date, and link one for review or dismiss it. | "Suggested from filings" section of `/admin/ballot-measures/{measure}/committees` |
| Ballot-measure committees | Link a campaign committee to the measure it supports or opposes (filer ID, name, side, and a link to the filing as evidence), and set each state's official campaign finance site. A link confirmed against the filing is verified on entry unless an integrity check flags it (state mismatch, a name that mentions another measure or the other side, a duplicate name, or evidence off the state's official site). | `/admin/ballot-measures/{measure}/committees` |
| Committee review queue | Verify or reject pending committee links, sorted by risk priority number (severity × occurrence × detectability). A nightly audit (`ballot-measures:audit-committee-links`) returns verified links to the queue when a new flag appears and reports to the pipeline health check. For California, rows show the filer's registered name and latest totals, a "Confirmed by filing" badge when the committee's filing declares the same measure and side, and warnings when the filer ID isn't found, the name doesn't match the filings, the filing declares another measure or side, the committee gave money to the other side of the measure, or the committee hasn't filed in 90 days. | `/admin/ballot-measure-committees` |
| Admin profile | Update administrator profile information. | `/admin/profile` |
| Admin two-factor security | Enroll/challenge/disable authenticator security and rotate recovery codes. | `/admin/security/2fa`, `/admin/2fa/challenge` |

## Mobile app status

The mobile app is Phase 12 of the project roadmap. A first CRUD version is built on the `feature/phase-12-mobile-app` branch (latest commit April 1, 2026) but has not been merged into `master` or released to app stores. Users reach every feature in the rest of this inventory through the web application, including on phone browsers.

On `master`, `mobile/` holds only the Android Firebase config, the iOS workspace file, and `.gitignore`. The app source, native projects, and the backend changes listed below exist only on the feature branch.

### First version on the feature branch

A React Native 0.73 app (`u9itus-mobile`) for iOS and Android, built with Metro, React Navigation, and a Zustand auth store. It talks to the Laravel backend through `mobile/src/services/ApiClient.ts`. Setup and run instructions are in the branch's `mobile/README.md`.

| Feature | Short description | Screen / status |
| --- | --- | --- |
| Sign-in and registration | Voter registration and role-based login for Voters and Politicians; the auth token is stored on the device. | `LoginScreen`, `RegisterScreen` |
| Voter onboarding | Welcome, profile setup (city, state, ZIP), and a verification step. The verification step is a placeholder that marks itself complete; it does not upload ID or proof of address yet. | `VoterOnboardingWelcomeScreen`, `VoterOnboardingProfileScreen`, `VoterOnboardingVerificationScreen` |
| Ad Viewing Room | Lists campaigns available to the signed-in voter and starts (claims) a campaign view. | `AdViewingRoomScreen` (voter home) |
| Politician profile | Shows a politician's profile and campaign, including voter video questions and campaign timer settings. | `PoliticianProfileScreen` |
| Video questions | Record a question with the camera or pick a video from the gallery and upload it for a watched campaign. | `VideoQuestionForm`, `VideoCaptureService` |
| Campaign creation | Politicians create a campaign with a title and message summary. Targeting, media, and budget are not yet in the mobile form. | `CreateCampaignScreen` (politician home) |
| Push notifications | Requests notification permission, receives foreground Firebase messages, and fetches the FCM token. The token is only logged; it is not yet sent to the server. | `NotificationService` |
| Location | Geolocation permission is requested during the auth flow. | `LoginScreen` / `RegisterScreen` |

The branch also changes the web backend:

- `POST /voter/watch/{token}/video-question` stores voter video questions on watch reports, with matching updates to the web watch screen and politician campaign analytics (see `doc/VOTER_VIDEO_QUESTIONS_FEATURE.md` on the branch).
- `GET /api/v1/campaign-timer-settings/{campaign}` returns a campaign's timer settings to the app.
- `TrackUserAccessContext` middleware and a `user_access_logs` table log each signed-in user's IP address, user agent, mobile-device flag, and a suspected-VPN signal, and keep the latest values on the user record.

Not in the first version: Citizen and Admin roles, earnings and payouts, the map, research and comparison tools, posts, events, groups, biometric sign-in, offline sync, and live video. Several of these are listed in the planned scope below.

### Planned mobile scope (Phase 12 roadmap)

From the Phase 12 roadmap in the README and the branch's `mobile/README.md`. Items beyond the first version above are goals, not shipped features.

| Planned capability | Intent |
| --- | --- |
| Platforms | Android (Android Studio) and iOS (Xcode) built with the Metro bundler; macOS desktop is listed as a stretch target. |
| Native push notifications | Deliver notifications through Firebase Cloud Messaging and Apple Push Notification service, reusing the Phase 18 notification preferences. |
| In-app notification center | Native notification list with badge counts. |
| Campaign viewing and earnings | Token-based campaign delivery with offline-first background sync, real-time wallet and earnings updates over WebSockets, and payout requests through a native payment sheet. |
| Live video | Politician-to-voter live streams over WebRTC/HLS built on the Reverb presence channels. |
| Biometric sign-in | Face ID, Touch ID, and Android fingerprint authentication. |
| Camera and photos | Profile pictures from the Photos app and camera capture for politician campaign video uploads. Voter video questions already use the camera in the first version. |
| Shared logic | Reuse the web API layer, Sanctum token authentication, referral and fraud rules, and notification preferences rather than reimplementing them. |

## Planned features (not yet implemented)

These features are proposed. Neither has routes, models, or screens in the current source.

### Political mail capture (PAC identification)

Users photograph political mail they receive, and together these user-submitted mailers identify the PACs behind them. The goal is that anyone holding a mailer can find out what the sending PAC actually represents.

**Why it matters.** PAC names often say little about who is behind them. A name like "Citizens for a Better Tomorrow" doesn't say who funds the PAC, which candidates or measures it backs or attacks, or what interests it serves. The "Paid for by" line on a mailer is often the only lead a voter has. Each user submission ties a real mailer to a committee record. Over time, the collected mail reveals each PAC's funders, targets, messages, and reach, so later users can recognize the PAC right away.

| Aspect | Proposed behavior |
| --- | --- |
| Audience | Signed-in Voters and Citizens submit mailers. Anyone can read the resulting PAC information. The mobile camera is the main entry point; the web portal also accepts photo uploads. |
| Instant lookup | The core user moment: photograph a mailer and see "Who sent this?" right away. If the sender is already known, show the PAC summary below. If not, show what the platform can find and invite the user to submit the mailer. |
| Capture | Take or upload photos of the front and back of a mailer, with the date received. The app records coarse location (ZIP code or district), never the street address. |
| Privacy before storage | Detect and blur the recipient name, address label, and barcodes on the device or before the image is stored or shown. Users confirm the redaction before submitting. |
| Sender identification | Run OCR and read the "Paid for by" disclaimer, committee ID numbers, and top-funder disclosures that some states require. Match the text to existing committee profiles (`/pacs/{committee}`) and politician profiles (`/p/{slug}`). An unknown sender creates a draft committee record for reviewers to research and match against FEC and state filings. The existing OCR candidate import pipeline and committee data are starting points. |
| Classification | Tag the sender type (candidate committee, party, super PAC or independent expenditure, ballot-measure committee, or dark-money group). Tag what each mailer supports or opposes (a candidate, a race, or a measure), its tone (promotional or attack), and its issue topics. |
| "What this PAC represents" | A plain-language summary at the top of each committee profile. It covers: who funds the PAC (top donors and industries from existing funding data); which candidates and measures it supports or opposes, based on its mail and spending; the issues and messages its mail pushes; where and when it mails; and related committees that share donors, vendors, or treasurers. Each claim links to its source, whether a mailer or a filing. |
| Mail gallery | Approved mailers appear on the sponsoring committee's profile, the targeted candidate's profile, and ballot-measure pages, as well as on a map layer showing where each sponsor is mailing. Candidate profiles show "mail about this candidate" grouped by sender, so voters can see who is attacking or promoting the candidate. |
| Review | Submissions enter an admin queue, like data-quality and chatter review, before anything is public. Reviewers confirm the sender match, the support/oppose tags, and the redaction; merge duplicates of the same mailer; and reject non-political or unsafe images. Duplicate reports from many users become a count of how widely a piece was mailed. |
| Contributor feedback | Submitters see the status of their submissions and which PAC each one was matched to. Later, submissions could count toward badges or rewards if they cannot be gamed. |
| Alerts | Users who save a district, follow a candidate, or save a measure can opt in to notices when a new PAC starts mailing there, and can discuss the mail in the related topic room. |
| Open questions | Retention period for original, unredacted images (ideally never stored); whether guests can submit or only look up; how to handle mail that has no disclaimer or names a committee not yet in any filing; how to word summaries so they stay factual and sourced; and rate limits to prevent flooding or coordinated false tagging. |

### Topic-based live rooms, group meetings, and messaging

Let users organize around political topics: drop into a live conversation about an issue, message within a group, and schedule meetings online or in person.

**Product vision.** The social layer is modeled on [Hilokal](https://www.hilokal.com/), a language-exchange app built around live audio "tables". On Hilokal, anyone can open a table or join one, participate as a speaker or as a listener who follows along in text chat, share images and emoji in that chat, and rely on host moderation tools. Its mobile app adds private "cafes" for closed groups and instant translation. For U9itus, the same pattern is applied to civic topics instead of languages: people meet others in their district who care about the same issue, and listen before they decide to speak.

| Aspect | Proposed behavior |
| --- | --- |
| Audience | Voters and Citizens host and join rooms and groups. Politicians can host or be invited to a room as a guest speaker, but do not see group messages by default. |
| Live topic rooms | The Hilokal "table" equivalent. A live audio room tied to a topic from the topic taxonomy and optionally to a district, city, race, or ballot measure. A lobby lists live rooms by topic and place, with a "Listen" button that joins immediately. |
| Speakers and listeners | Joining as a listener is the default. Listeners follow the live text chat and can request to speak. Hosts invite listeners on stage, mute speakers, and move them back to listening. |
| Room chat | Text chat alongside the audio, with images, emoji, and links. Links to candidates, measures, and PAC profiles expand into preview cards from existing platform data. |
| Private rooms | The Hilokal "private cafe" equivalent. A group can hold members-only rooms and recurring meetings. |
| Topic groups | Extend neighborhood groups (`/groups`) so a group can be based on a topic, optionally limited to a place. Suggest rooms and groups from each user's issue badges, followed politicians, saved areas, and followed causes. |
| Group messaging | A persistent message thread for each group, with real-time delivery over Reverb, unread counts in the notification center, and mobile push. The platform has no general chat inbox today; the only messaging is campaign-linked messages and questions. |
| Scheduled meetings | Schedule a room or in-person meeting with an agenda and time. Reuse group events, RSVPs, ICS export, and reminders; followers get notified when a scheduled room goes live. |
| Candidate town halls | A politician-hosted room works as a live town hall. Questions submitted in chat can feed the existing campaign Q&A and answered-question display on `/p/{slug}`. |
| Translation | Following Hilokal's instant translation, translate room chat (and later live captions) so Spanish-speaking and other non-English voters can take part in the same room. |
| Technology | Live audio uses the WebRTC stack planned for Phase 12, with a hosted media server for rooms larger than a few speakers. Mobile is the main surface; the web gets a listen-and-chat view. |
| Roles and moderation | Hosts and co-hosts manage the stage, remove chat messages, and remove or block users. Users can report a room, message, or person to an admin moderation queue. Admins can close a live room. |
| Safety | Verified accounts to host or speak, rate limits on chat, blocking, no exposure of member email or phone numbers, and clear rules against harassment, threats, and election misinformation. |
| Monetization model | Follow Hilokal's approach of charging for convenience and status, never for access. Hilokal makes money from Premium subscriptions (perks such as bigger rooms, custom emoji, filters, analytics, and no ads), a virtual currency called Beans used for tips and paid sessions, paid tables run by approved trainers, and ads shown to free users. For U9itus: joining rooms and reading civic research stay free. A subscription sells extras such as larger rooms, custom room backgrounds, AI-written briefings, advanced alerts, and an ad-free experience. Paid rooms are limited to approved non-campaign hosts, such as policy briefings, "how to run for office" workshops, and ballot-measure explainers. Clearly labeled sponsored rooms fit the existing model where advertisers pay and voters earn. |
| Tipping and election law | Tips or paid entry to a room hosted by a candidate, campaign, or committee could count as a campaign contribution, with donor disclosure, limits, and prohibited-donor rules. Allow tips only to non-campaign hosts, or route any payment to a candidate through a compliant contribution flow. Get an election lawyer's review before building any payment feature in rooms. |
| Open questions | Whether rooms are recorded or transcribed, and who can access recordings; how to moderate live audio at scale; whether campaigns may pay to promote or host rooms, and what election-law disclosure applies; minimum age; message retention; and whether topic groups and neighborhood groups share one model. |

## Gaps and availability qualifications found during review

| Item | Finding and effect on the user experience |
| --- | --- |
| How It Works, Pricing, Contact | `/how-it-works`, `/pricing`, and `/contact` are registered, but their referenced `standalone.how-it-works`, `standalone.pricing`, and `standalone.contact` Blade views are absent. Treat these as incomplete public pages, not working features. A contact submission handler exists but does not supply the missing page. |
| Duplicate About route | `standalone.php` references an absent `standalone.about` view, but `web.php` later registers `/about` with the existing `about` view. The effective web registration points to the existing page; the earlier duplicate is cleanup debt. |
| Citizen onboarding | `OnboardingService::CITIZEN_PHASES` has no required phases; there is no implemented Citizen wizard to inventory. |
| Public preview versus paid viewing | Public campaign previews do not by themselves earn rewards. Earning requires the eligible authenticated viewing flow and its completion/verification rules. |
| Guest trials | Temporary voter sessions depend on a platform flag. Monetization is blocked for these sessions, and trial access should not be described as unrestricted public access. |
| Public profile/data coverage | News, funding, congressional records, songs, initiatives, chatter, and other sections may be hidden or empty when data/settings do not support them. |
| Service-dependent features | Payments, identity verification, email/SMS/push, map data/geocoding, streaming, S3 processing, Early-bank, and WebMCP browser integration require compatible configuration/services. Source implementation does not verify their live deployment health. |
| Payment amounts | Prices, commissions, and payout thresholds are configurable. Older README examples are not authoritative promises of current earnings. |
| Payout methods | The preference screen offers PayPal and Cash App; this review does not establish automatic Cash App payout execution. Stripe wallet/verification and PayPal payout infrastructure are separate flows. |
| Citizen campaign replies | Citizen campaign questions are stored and emailed to the sponsor. There is no public Citizen Q&A board or Citizen reply route comparable to the Politician campaign reply route. |
| No dedicated public Citizen profile | Citizen businesses appear on the map and Citizen authors/hosts appear through posts/events. The route set does not provide a `/citizen/{slug}` public profile equivalent to `/p/{slug}`. |
| Ballot-measure funding coverage | Dollar totals, donors and transfers are imported nightly for three states: California (CAL-ACCESS export, `ballot-measures:import-cal-access`), Florida (Division of Elections query forms, one committee at a time, `ballot-measures:import-florida`) and Texas (Texas Ethics Commission export, `ballot-measures:import-texas`). Other states, including Michigan, whose MiTN search doesn't allow automated queries, show committee links without amounts. Florida doesn't publish cash on hand in a readable form, and its committees are matched to donors and payees by name. Texas covers state-level filers only; committees filing just with a city or county clerk aren't included. California committees filing the short Form 450 show no amounts, and California link suggestions come only from late contribution reports (Form 497). California and Florida keep donors for the latest year only, so a decided California or Florida measure shows totals but may show no donor list. Unitemized contributions under $100 are in California totals but not its donor lists. Few committees declare a measure on their filings (70 of 2,624 California Form 460 filers in 2026), and Texas declarations are often lists or omit the place, so the "Confirmed by filing" check applies to a minority of links. The section appears only after an admin sets the state's finance site or verifies at least one committee. |
| Mobile and legacy scope | A first CRUD version of the mobile app exists on the unmerged `feature/phase-12-mobile-app` branch; no mobile app has been released and `master` has only a scaffold. Historical code in `legacy/` is not proof of current features. See [Mobile app status](#mobile-app-status). |

## Source map for maintenance

Use these repository sources to verify or update the inventory when features change:

- [Web routes](../routes/web.php), [portal and public routes](../routes/standalone.php), and [API routes](../routes/api.php): page locations, action endpoints, and route access gates.
- [Standalone controllers](../app/Http/Controllers/Standalone/): screen data, validation, lifecycle actions, and controller-level authorization.
- [Portal/public views](../resources/views/standalone/) and [shared components](../resources/views/components/): the rendered feature surfaces.
- [Map browser code](../resources/js/map/), [comparison browser code](../resources/js/compare/), and [WebMCP registration](../resources/js/webmcp/index.js): interactive research tools.
- [Admin permission mapping](../config/admin_routes.php): staff capability restrictions.
- [Onboarding service](../app/Services/OnboardingService.php): implemented role onboarding phases.
- [Citizen ad types](../app/Enums/CitizenAdType.php) and [Citizen campaign validation](../app/Http/Requests/CreateCitizenCampaignRequest.php): campaign categories and required targeting/content fields.
- The `feature/phase-12-mobile-app` branch (`mobile/src/`, `mobile/README.md`), the [mobile workspace](../mobile/) on `master`, and the Phase 12 section of the [README](../README.md#mobile-application-development-phase-12): mobile app status and roadmap.
- [Contributor controller](../app/Http/Controllers/Standalone/ChatterContributorController.php) and [browser extension](../browser-extension/chatter-clipper/): source-submission workflow.

Validation performed for this document: reviewed route definitions against relevant templates/controllers and browser modules; identified missing informational-page templates; checked the documentation diff and source-link targets. No application code was changed, and no runtime test suite or production walkthrough was performed.
