<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Machinery / Calibration configuration
     |--------------------------------------------------------------------------
     |
     | calibration_alert_days controls how many days in advance a calibration
     | should be considered "due soon" for dashboards and notifications.
     |
     */

    'calibration_alert_days' => env('MACHINERY_CALIBRATION_ALERT_DAYS', 15),
];
