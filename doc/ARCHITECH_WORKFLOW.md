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

#### Wix Dashboard (Politician)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - POST /api/politicians
    - POST /api/campaigns
    - GET /api/campaigns/{id}
    - POST /api/campaigns/{id}/payment

#### Wix Dashboard (Voter)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - POST /api/voters
    - GET /api/voters/{id}/campaigns/available
    - POST /api/view-sessions
    - GET /api/voters/{id}/earnings

#### Wix Dashboard (Admin)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - GET /api/admin/campaigns/pending
    - POST /api/admin/campaigns/{id}/approve
    - POST /api/admin/campaigns/{id}/reject
    - GET /api/admin/fraud-reviews
    - POST /api/admin/payouts/process

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

#### Auth Service (Wix OAuth)

- **Type**: Service A
- **Technology**: Laravel + Wix SDK
- **Responsibilities**:
    - Wix OAuth flow (`/wix/oauth/callback`)
    - Instance token validation
    - Site installation tracking
    - Member authentication
- **External Dependency**: Wix API (https://www.wixapis.com)

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
