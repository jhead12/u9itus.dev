# U9itus System Architecture - Architech Canvas Workflow

## Overview

This document describes the complete system architecture for U9itus that can be built in Architech Canvas at https://www.architech-dev.tech/canvas

---

## Components (Nodes)

### 1. **User Layer**

#### Politician User

- **Type**: User
- **Description**: Political candidate/office holder who creates campaigns
- **Actions**: Register, Create Campaign, Add Payment Method, Monitor Campaign

#### Voter User

- **Type**: User
- **Description**: Registered voter who watches political ads and earns money
- **Actions**: Register, Watch Video, Generate Referral Code, Request Payout

#### Admin User

- **Type**: User
- **Description**: Platform administrator
- **Actions**: Approve/Reject Campaigns, Review Fraud, Process Payouts

---

### 2. **Frontend Layer**

#### Politician Dashboard

- **Type**: UI
- **Technology**: Laravel Blade + Tailwind CSS
- **URL**: /politician/\*
- **Endpoints Used**:
    - POST /api/v1/politicians
    - POST /api/v1/politicians/{id}/campaigns
    - GET /api/v1/politicians/{id}/campaigns
    - POST /api/v1/politicians/{id}/billing/purchase

#### Voter Dashboard

- **Type**: UI
- **Technology**: Laravel Blade + Tailwind CSS
- **URL**: /voter/\*
- **Endpoints Used**:
    - POST /api/v1/voters
    - GET /api/v1/voters/{id}/campaigns
    - POST /api/v1/voters/{id}/campaigns/{cid}/watch
    - GET /api/v1/voters/{id}/earnings

#### Admin Dashboard

- **Type**: UI
- **Technology**: Laravel Blade + Tailwind CSS
- **URL**: /admin/\*
- **Endpoints Used**:
    - GET /api/v1/admin/campaigns/pending
    - POST /api/v1/admin/campaigns/{id}/approve
    - POST /api/v1/admin/campaigns/{id}/reject
    - GET /api/v1/admin/voters/flagged
    - POST /api/v1/admin/payouts/process

#### Laravel Welcome Page

- **Type**: UI
- **Technology**: Laravel Blade Template
- **URL**: https://u9itus-production.up.railway.app
- **Shows**: Revenue model, profit margins, platform economics

---

### 3. **API Gateway / Backend**

#### Laravel API Server

- **Type**: Server
- **Technology**: Laravel 12 / PHP 8.2
- **Port**: 8080
- **URL**: https://u9itus-production.up.railway.app
- **Hosting**: Railway.app
- **Responsibilities**:
    - RESTful API endpoints
    - Business logic orchestration
    - Authentication & authorization
    - Request validation
    - Response formatting

---

### 4. **Service Layer**

#### Auth Service (Laravel Sanctum)

- **Type**: Service A
- **Technology**: Laravel Sanctum + Spatie Permission
- **Responsibilities**:
    - Session-based authentication
    - API token management
    - Role-based access control (admin, politician, voter)
    - Email verification

#### Campaign Management Service

- **Type**: Service B
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Campaign creation & validation
    - Budget management
    - Status transitions (pending → active → completed)
    - View count tracking

---

... (document truncated here for brevity, original content preserved in repo)
