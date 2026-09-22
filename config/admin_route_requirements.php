<?php

// Shared screens/actions expose or change more than one protected domain.
return [
    'admin.users.show' => ['finance.reports.view'],
    'admin.users.destroy' => ['finance.refunds.manage'],
];
