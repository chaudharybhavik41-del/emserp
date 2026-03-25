<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$report = new \App\ReportsHub\Reports\StoreIssueAccountingReport();
if (method_exists($report, 'headerData')) {
    echo "Method headerData EXISTS\n";
} else {
    echo "Method headerData DOES NOT EXIST\n";
}
