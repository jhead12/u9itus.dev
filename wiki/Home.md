# U9itus Wiki

Welcome to the **U9itus** project wiki. U9itus is a standalone political advertising platform built on Laravel 12 that connects politicians directly with voters through paid video messages and live feeds.

> _"Regardless of how much artificial intelligence is used, without the human element the production that AI affords is all for naught."_ — Head Enterprises

## Navigation

| Page | Description |
|------|-------------|
| [Getting Started](Getting-Started.md) | Installation, environment setup, and first run |
| [Architecture](Architecture.md) | Tech stack, directory structure, and system overview |
| [Business Model](Business-Model.md) | Per-view economics, pricing, and referral system |
| [User Roles](User-Roles.md) | Politician, Voter, and Admin role descriptions |
| [Routes and API](Routes-and-API.md) | All web routes and REST API endpoint reference |
| [Database Schema](Database-Schema.md) | Database tables and service layer overview |
| [Security and Fraud](Security-and-Fraud.md) | Auth, token delivery, fraud scoring, and fraud prevention |
| [Development](Development.md) | Developer workflow, commands, and coding standards |
| [Deployment](Deployment.md) | Railway production deployment guide |
| [Implementation Progress](Implementation-Progress.md) | Phase-by-phase feature tracker |

## Quick Links

- **Production URL:** https://u9itus-production.up.railway.app
- **Repository:** https://github.com/jhead12/u9itus.dev
- **Roadmap:** https://jonathan-head.com/un9itus/

## At a Glance

| Item | Value |
|------|-------|
| Framework | Laravel 12 (PHP 8.2+) |
| Frontend | Blade + Tailwind CSS + Alpine.js |
| Database | SQLite (dev) / MySQL (production) |
| Payments | Stripe (politician billing) |
| Real-time | Laravel Reverb (WebSockets) |
| Deployment | Railway.app |
| Test count | 275 tests, 776 assertions |
