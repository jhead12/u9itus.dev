# Id.me vs. Current Manual KYC — Decision Checklist

**Platform:** u9itus / Dial4Dough
**Date:** April 11, 2026
**Purpose:** Evaluate replacing the manual in-house KYC system with Id.me identity verification

---

## Section 1: Identity Verification Capability

| Control | Current KYC (Manual) | Id.me |
|---|---|---|
| User uploads government ID document | ✅ (self-hosted in storage/public/kyc/) | ✅ (Id.me handles doc capture + liveness) |
| Document authenticity check | ❌ Admin eye-check only | ✅ Automated via Id.me proofing engine |
| Liveness / selfie check | ❌ None | ✅ Biometric liveness built in |
| Admin review queue needed | ✅ Required (/admin/kyc queue) | ❌ Eliminated — fully automated |
| Review turnaround | Delayed (manual) | Real-time or minutes |
| Fraud/spoof resistance | Low | High (biometric + document forensics) |
| Politician .gov email verification | ✅ ProfileVerificationService | ⚠️ Keep as-is — Id.me does not check .gov email |

Id.me vs. Current Manual KYC — Decision Checklist
Section 1: Identity Verification Capability
Control	Current KYC (Manual)	Id.me
User uploads government ID document	✅ (self-hosted in storage/public/kyc/)	✅ (Id.me handles doc capture + liveness)
Document authenticity check	❌ Admin eye-check only	✅ Automated via Id.me's proofing engine
Liveness / selfie check	❌ None	✅ Biometric liveness built in
Admin review queue needed	✅ Required (your /admin/kyc queue)	❌ Eliminated — fully automated
Review turnaround	Delay (manual)	Real-time or minutes
Fraud/spoof resistance	Low	High (biometric + document forensics)
Politician .gov email verification	✅ ProfileVerificationService	⚠️ Would need to keep — Id.me does not check .gov email
Section 2: User Trust & Experience
Factor	Current KYC	Id.me
Brand recognition	Unknown	✅ Trusted by VA, IRS, USPS, 100M+ users
Friction for users who already have Id.me	High (re-upload docs)	Low (single click, reuse existing credentials)
Friction for new users	Medium	Medium (same doc upload, but slicker UX)
Mobile-friendly flow	Depends on your UI	✅ Native iOS/Android SDK available
Support burden for failed verifications	❌ Falls on your team	✅ Id.me support handles it
Section 3: Auth Integration (Laravel-Specific)
Task	Current	Id.me
OAuth provider	❌ Not used	OAuth 2.0 via api.id.me/oauth/authorize + api.id.me/oauth/token
PHP library	N/A	league/oauth2-client (already in PHP ecosystem; add GenericProvider)
User attributes returned	Manual form fields	identity scope: name, DOB, address, SSN-last-4; also community scopes (military, student, etc.)
Scope for basic identity	N/A	identity + email
Laravel Socialite	Not used	Can add a community Id.me Socialite driver or use league/oauth2-client directly
State/CSRF protection	N/A	Required — tracked via $_SESSION['oauth2state'] in sample
Where to store result	users.kyc_status	Add idme_uuid + idme_verified_at columns; map to existing kyc_status = approved
Replaces	uploadKycDocument() in VoterController + PoliticianController	✅ Both can be replaced
Admin KYC queue	/admin/kyc controller + views	✅ Eliminated for standard users
Section 4: Compliance Coverage — What Id.me Covers vs. What It Doesn't
Compliance Obligation	Id.me Covers?	Notes
Identity proofing (real person, real name)	✅ Yes	NIST IAL2-level proofing
Age verification (COPPA — must be 18+)	✅ Yes — DOB returned in identity scope	You gate on that attribute
Government ID document check	✅ Yes	
AML / OFAC sanctions screening	❌ No	Id.me does not run sanctions lookups — you still need this before payouts
1099-K / IRS tax reporting	❌ No	Still absent from platform — needed above $600/year per voter
FinCEN MSB registration	❌ No	Paying voters is money transmission — still your legal obligation
FEC contribution limit enforcement	❌ No	Still display-only in platform
Politician .gov email verification	❌ No	Keep ProfileVerificationService as-is
Duplicate account detection	⚠️ Partial	Id.me UUID is unique per person — helps detect dupes if you enforce 1 account per UUID
Section 5: Security Posture
Issue	Current KYC	Id.me
KYC docs stored in public disk (storage/public/kyc/)	⚠️ Security risk (accessible via URL)	✅ Eliminated — no docs stored on your server
Admin manual approval = insider risk	⚠️ Exists	✅ Eliminated for automated checks
Payout not gated on kyc_status = approved	❌ Gap in payout logic	✅ Easier to enforce: gate payout on idme_verified_at IS NOT NULL
Document data retention liability	❌ You store PII docs	✅ Eliminated — Id.me stores and holds liability
Section 6: Cost & Operational Trade-offs
Factor	Current KYC	Id.me
Verification cost	$0 (admin time)	Id.me charges per verification — confirm pricing via their sales team
Admin headcount needed	❌ Requires reviewer(s)	✅ None
Integration effort	Already built	~1–2 sprints: OAuth flow, new columns, remove old upload routes
Vendor dependency	None	Dependent on Id.me uptime and pricing policy
Id.me account prerequisite	N/A	Users must create Id.me account if they don't have one
Section 7: Recommended Decision
Scenario	Recommendation
You want to eliminate admin KYC review burden	✅ Switch to Id.me
You want better fraud resistance and biometric checks	✅ Switch to Id.me
You want to fix the public-disk document storage vulnerability	✅ Switching removes the problem entirely
You need AML/OFAC compliance before releasing payouts	❌ Id.me alone is not enough — add a dedicated AML layer (e.g., Persona, Unit21, or Stripe Identity + AML)
You need tax reporting	❌ Neither solves this — build 1099-K logic separately
Critical Gaps That Neither Id.me Nor Current KYC Solves
These remain open regardless of which verification path you choose:

OFAC/sanctions screening before voter payouts — legally required for money transmission
1099-K IRS reporting — any voter earning $600+ annually triggers a reporting obligation
Payout gating — kyc_status = approved (or idme_verified_at) must block PayPalPayoutService / CashAppPayoutService for unverified users
KYC docs on public disk — must move to private disk even if you keep manual KYC temporarily