# Admin Console Widget Reference

## Overview

This document provides a complete reference for the Admin Console widget component IDs and structure. Use this reference when working with the widget elements in code or when debugging the admin dashboard.

## Widget Structure

The Admin Console is organized into a main layout container with a navigation sidebar and multiple content sections.

---

## Root Elements

| Component ID      | Element Name      | Type          | Description                  |
| ----------------- | ----------------- | ------------- | ---------------------------- |
| `#comp-mlj0bmmb`  | box2              | Container     | Main wrapper container       |
| `#comp-mlj0bmme`  | Admin Console     | AppController | Primary app controller       |
| `#comp-mlj0bmmg3` | box1              | Container     | Admin Console root container |
| `#comp-mlj0cf90`  | adminConsoleTitle | StyledText    | Main title header            |

---

## Layout Structure

### Main Layout Container

**ID:** `#comp-mlj0cg00` - `mainLayoutContainer`

Contains two primary areas:

- Navigation Sidebar (`#comp-mlj0cg85`)
- Content Container (`#comp-mlj0cj4d`)

---

## Navigation Sidebar

**Container ID:** `#comp-mlj0cg85` - `navigationSidebar`

Navigation buttons for switching between admin sections:

| Component ID     | Element Name         | Label              |
| ---------------- | -------------------- | ------------------ |
| `#comp-mlj0cgi2` | campaignApprovalsNav | Campaign Approvals |
| `#comp-mlj0cgwz` | fraudDashboardNav    | Fraud Dashboard    |
| `#comp-mlj0chdw` | payoutQueueNav       | Payout Queue       |
| `#comp-mlj0chzf` | analyticsNav         | Analytics          |
| `#comp-mlj0ci7y` | auditTrailNav        | Audit Trail        |

### Usage Example

```javascript
$w("#campaignApprovalsNav").onClick(() => {
    showSection("campaignApprovals");
});
```

---

## Content Sections

### 1. Campaign Approvals Section

**Container ID:** `#comp-mlj0cjcn` - `campaignApprovalsSection`

**Title:** `#comp-mlj0cjs5` - `campaignApprovalsTitle`

**Repeater:** `#comp-mlj0ck9e` - `campaignItems`

#### Repeater Item Structure

Each repeater item (`#comp-mlj0ck9i4__item1`) contains:

| Component ID Pattern    | Element Name   | Type           | Description     |
| ----------------------- | -------------- | -------------- | --------------- |
| `#comp-mlj0cksx__item1` | campaignName   | StyledText     | Campaign title  |
| `#comp-mlj0cl8g__item1` | advertiserName | StyledText     | Advertiser name |
| `#comp-mlj0clmi__item1` | budgetAmount   | StyledText     | Campaign budget |
| `#comp-mlj0cm20__item1` | flaggedScore   | StyledText     | Risk/flag score |
| `#comp-mlj0cmfh__item1` | approveButton  | StylableButton | Approve action  |
| `#comp-mlj0cmu9__item1` | rejectButton   | StylableButton | Reject action   |

#### Usage Example

```javascript
$w("#campaignItems").onItemReady(($item, itemData, index) => {
    $item("#campaignName").text = itemData.name;
    $item("#advertiserName").text = itemData.advertiser;
    $item("#budgetAmount").text = `$${itemData.budget}`;
    $item("#flaggedScore").text = itemData.riskScore;

    $item("#approveButton").onClick(() => {
        approveCampaign(itemData.id);
    });

    $item("#rejectButton").onClick(() => {
        rejectCampaign(itemData.id);
    });
});
```

---

### 2. Fraud Dashboard Section

**Container ID:** `#comp-mlj0cn82` - `fraudDashboardSection`

**Title:** `#comp-mlj0cntb` - `fraudDashboardTitle`

**Metrics Container:** `#comp-mlj0co7c` - `fraudMetricsContainer`

#### Fraud Metrics

| Component ID     | Element Name               | Metric                    |
| ---------------- | -------------------------- | ------------------------- |
| `#comp-mlj0cos4` | flaggedVotersCount         | Count of flagged voters   |
| `#comp-mlj0cpu6` | deviceFingerprintAnomalies | Device fingerprint issues |
| `#comp-mlj0cq2l` | ipAnomalies                | IP address anomalies      |

**Repeater:** `#comp-mlj0cqc1` - `flaggedVotersList`

#### Flagged Voters Repeater Structure

Each repeater item (`#comp-mlj0cqc35__item1`) contains:

| Component ID Pattern    | Element Name   | Type       | Description           |
| ----------------------- | -------------- | ---------- | --------------------- |
| `#comp-mlj0cqx4__item1` | voterID        | StyledText | Voter identifier      |
| `#comp-mlj0crc4__item1` | flaggingReason | StyledText | Reason for flag       |
| `#comp-mlj0crsl__item1` | riskScore      | StyledText | Risk assessment score |

#### Usage Example

```javascript
$w("#flaggedVotersCount").text = `${fraudData.flaggedCount} Flagged Voters`;
$w("#deviceFingerprintAnomalies").text = `${fraudData.deviceAnomalies} Devices`;
$w("#ipAnomalies").text = `${fraudData.ipAnomalies} IP Addresses`;

$w("#flaggedVotersList").data = fraudData.voters;
```

---

### 3. Payout Queue Section

**Container ID:** `#comp-mlj0cs8a` - `payoutQueueSection`

**Title:** `#comp-mlj0cst7` - `payoutQueueTitle`

**Actions Container:** `#comp-mlj0ct7d` - `payoutActionsContainer`

#### Action Buttons

| Component ID     | Element Name       | Action                   |
| ---------------- | ------------------ | ------------------------ |
| `#comp-mlj0ctsb` | processBatchButton | Process selected payouts |
| `#comp-mlj0cu8c` | holdAllButton      | Place all on hold        |

**Repeater:** `#comp-mlj0cvbp` - `payoutItems`

#### Payout Items Repeater Structure

Each repeater item (`#comp-mlj0cvbr7__item1`) contains:

| Component ID Pattern    | Element Name      | Type          | Description       |
| ----------------------- | ----------------- | ------------- | ----------------- |
| `#comp-mlj0cvqs__item1` | payoutID          | StyledText    | Payout identifier |
| `#comp-mlj0cvzf__item1` | payoutAmount      | StyledText    | Payment amount    |
| `#comp-mlj0cw8z__item1` | recipient         | StyledText    | Recipient name    |
| `#comp-mlj0cwiz__item1` | selectionCheckbox | CheckboxInput | Selection control |

#### Usage Example

```javascript
$w("#processBatchButton").onClick(() => {
    const selectedPayouts = $w("#payoutItems").data.filter((item, index) => {
        return $w("#payoutItems").data[index].selected;
    });
    processBatch(selectedPayouts);
});

$w("#payoutItems").onItemReady(($item, itemData, index) => {
    $item("#payoutID").text = itemData.id;
    $item("#payoutAmount").text = `$${itemData.amount.toFixed(2)}`;
    $item("#recipient").text = itemData.recipient;
    $item("#selectionCheckbox").checked = itemData.selected || false;
});
```

---

### 4. Analytics Section

**Container ID:** `#comp-mlj0cwtk` - `analyticsSection`

**Title:** `#comp-mlj0cx98` - `analyticsTitle`

**Metrics Container:** `#comp-mlj0cxm9` - `metricsContainer`

#### Analytics Metrics

| Component ID     | Element Name     | Metric               |
| ---------------- | ---------------- | -------------------- |
| `#comp-mlj0cz40` | dailyViewsMetric | Daily views count    |
| `#comp-mlj0czdt` | revenueMetric    | Total revenue        |
| `#comp-mlj0czn9` | payoutsMetric    | Total payouts        |
| `#comp-mlj0czwi` | fraudRateMetric  | Fraud detection rate |

#### Usage Example

```javascript
function updateAnalytics(data) {
    $w("#dailyViewsMetric").text = `${data.views.toLocaleString()} Views`;
    $w("#revenueMetric").text =
        `$${data.revenue.toLocaleString("en-US", { minimumFractionDigits: 2 })}`;
    $w("#payoutsMetric").text =
        `$${data.payouts.toLocaleString("en-US", { minimumFractionDigits: 2 })}`;
    $w("#fraudRateMetric").text = `${data.fraudRate.toFixed(2)}%`;
}
```

---

### 5. Audit Trail Section

**Container ID:** `#comp-mlj0d05p` - `auditTrailSection`

**Title:** `#comp-mlj0d0kx` - `auditTrailTitle`

**Repeater:** `#comp-mlj0d0ya` - `auditLogItems`

#### Audit Log Repeater Structure

Each repeater item (`#comp-mlj0d0yc__item1`) contains:

| Component ID Pattern    | Element Name | Type       | Description               |
| ----------------------- | ------------ | ---------- | ------------------------- |
| `#comp-mlj0d20k__item1` | timestamp    | StyledText | Event timestamp           |
| `#comp-mlj0d29y__item1` | action       | StyledText | Action performed          |
| `#comp-mlj0d2ki__item1` | user         | StyledText | User who performed action |
| `#comp-mlj0d2ux__item1` | details      | StyledText | Additional details        |

#### Usage Example

```javascript
$w("#auditLogItems").onItemReady(($item, itemData, index) => {
    $item("#timestamp").text = new Date(itemData.timestamp).toLocaleString();
    $item("#action").text = itemData.action;
    $item("#user").text = itemData.user;
    $item("#details").text = itemData.details || "N/A";
});

// Load audit log data
$w("#auditLogItems").data = auditTrailData;
```

---

## Widget Initialization

### Basic Setup

```javascript
$w.onReady(function () {
    // Initialize navigation
    setupNavigation();

    // Load initial section
    showSection("campaignApprovals");

    // Initialize all sections
    initializeCampaignApprovals();
    initializeFraudDashboard();
    initializePayoutQueue();
    initializeAnalytics();
    initializeAuditTrail();
});
```

### Property Change Handler

```javascript
$widget.onPropsChanged((oldProps, newProps) => {
    // Handle property changes
    if (newProps.activeSection !== oldProps.activeSection) {
        showSection(newProps.activeSection);
    }

    if (newProps.refreshData) {
        refreshAllSections();
    }
});
```

---

## Helper Functions

### Section Visibility Management

```javascript
function showSection(sectionName) {
    // Hide all sections
    $w("#campaignApprovalsSection").collapse();
    $w("#fraudDashboardSection").collapse();
    $w("#payoutQueueSection").collapse();
    $w("#analyticsSection").collapse();
    $w("#auditTrailSection").collapse();

    // Show requested section
    const sectionMap = {
        campaignApprovals: "#campaignApprovalsSection",
        fraudDashboard: "#fraudDashboardSection",
        payoutQueue: "#payoutQueueSection",
        analytics: "#analyticsSection",
        auditTrail: "#auditTrailSection",
    };

    const sectionId = sectionMap[sectionName];
    if (sectionId) {
        $w(sectionId).expand();
    }
}
```

### Navigation Setup

```javascript
function setupNavigation() {
    $w("#campaignApprovalsNav").onClick(() => showSection("campaignApprovals"));
    $w("#fraudDashboardNav").onClick(() => showSection("fraudDashboard"));
    $w("#payoutQueueNav").onClick(() => showSection("payoutQueue"));
    $w("#analyticsNav").onClick(() => showSection("analytics"));
    $w("#auditTrailNav").onClick(() => showSection("auditTrail"));
}
```

---

## Component ID Quick Reference

### Complete Alphabetical List

```
#comp-mlj0bmme - Admin Console (AppController)
#comp-mlj0bmmb - box2 (Container)
#comp-mlj0bmmg3 - box1 (Admin Console Root)
#comp-mlj0cf90 - adminConsoleTitle (StyledText)
#comp-mlj0cg00 - mainLayoutContainer (Container)
#comp-mlj0cg85 - navigationSidebar (Container)
#comp-mlj0cgi2 - campaignApprovalsNav (StylableButton)
#comp-mlj0cgwz - fraudDashboardNav (StylableButton)
#comp-mlj0chdw - payoutQueueNav (StylableButton)
#comp-mlj0chzf - analyticsNav (StylableButton)
#comp-mlj0ci7y - auditTrailNav (StylableButton)
#comp-mlj0cj4d - contentContainer (Container)
#comp-mlj0cjcn - campaignApprovalsSection (Container)
#comp-mlj0cjs5 - campaignApprovalsTitle (StyledText)
#comp-mlj0ck9e - campaignItems (Repeater)
#comp-mlj0cn82 - fraudDashboardSection (Container)
#comp-mlj0cntb - fraudDashboardTitle (StyledText)
#comp-mlj0co7c - fraudMetricsContainer (Container)
#comp-mlj0cos4 - flaggedVotersCount (StyledText)
#comp-mlj0cpu6 - deviceFingerprintAnomalies (StyledText)
#comp-mlj0cq2l - ipAnomalies (StyledText)
#comp-mlj0cqc1 - flaggedVotersList (Repeater)
#comp-mlj0cs8a - payoutQueueSection (Container)
#comp-mlj0cst7 - payoutQueueTitle (StyledText)
#comp-mlj0ct7d - payoutActionsContainer (Container)
#comp-mlj0ctsb - processBatchButton (StylableButton)
#comp-mlj0cu8c - holdAllButton (StylableButton)
#comp-mlj0cvbp - payoutItems (Repeater)
#comp-mlj0cwtk - analyticsSection (Container)
#comp-mlj0cx98 - analyticsTitle (StyledText)
#comp-mlj0cxm9 - metricsContainer (Container)
#comp-mlj0cz40 - dailyViewsMetric (StyledText)
#comp-mlj0czdt - revenueMetric (StyledText)
#comp-mlj0czn9 - payoutsMetric (StyledText)
#comp-mlj0czwi - fraudRateMetric (StyledText)
#comp-mlj0d05p - auditTrailSection (Container)
#comp-mlj0d0kx - auditTrailTitle (StyledText)
#comp-mlj0d0ya - auditLogItems (Repeater)
```

---

## Notes

- **Repeater Items:** Component IDs for repeater items follow the pattern `{baseId}__item1`, `__item2`, etc.
- **Item Selection:** Use `$item()` within `onItemReady()` to access repeater item elements
- **Visibility:** Use `.collapse()` and `.expand()` for section visibility management
- **Data Binding:** Bind repeater data using the `.data` property
- **Event Handling:** Attach event handlers using `.onClick()`, `.onChange()`, etc.

---

## Related Documentation

- [WIX Deployment Guide](./WIX_DEPLOYMENT_GUIDE.md)
- [Backend API Documentation](../../README.md)
- [Admin Console Implementation Plan](../U9ITUS_IMPLEMENTATION_PLAN.md)

---

**Last Updated:** February 13, 2026  
**Widget Version:** Admin Console v1.0  
**Maintainer:** U9itus Development Team
