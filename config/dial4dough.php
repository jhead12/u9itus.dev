<?php

return [
    /**
     * Head Enterprises fee percentage charged on each campaign
     */
    'head_enterprises_fee_percent' => env('HEAD_ENTERPRISES_FEE_PERCENT', 15.0),

    /**
     * Number of hours before an assignment expires
     */
    'assignment_expiry_hours' => env('ASSIGNMENT_EXPIRY_HOURS', 24),

    /**
     * Minimum watch time percentage required for payment
     */
    'min_watch_time_percent' => env('MIN_WATCH_TIME_PERCENT', 80),

    /**
     * Minimum payout amount to process viewer payments
     */
    'min_payout_amount' => env('MIN_PAYOUT_AMOUNT', 25.00),

    /**
     * Default payment per view if not specified
     */
    'default_payment_per_view' => env('DEFAULT_PAYMENT_PER_VIEW', 1.00),

    /**
     * Maximum video duration in seconds (20 seconds)
     */
    'max_video_duration' => env('MAX_VIDEO_DURATION', 20),

    /**
     * Minimum video duration in seconds (10 seconds)
     */
    'min_video_duration' => env('MIN_VIDEO_DURATION', 10),
];
