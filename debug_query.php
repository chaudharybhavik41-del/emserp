<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$issueId = 3;
$report = new \App\ReportsHub\Reports\StoreIssueAccountingReport();
$filters = ['issue_id' => $issueId];
$query = $report->query($filters);

echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n";

$rows = $query->get();
echo "Count: " . $rows->count() . "\n";
foreach($rows as $r) {
    echo "ID: " . $r->issue_id . " No: " . $r->issue_number . " Project: " . $r->project_name . " Item: " . $r->item_name . "\n";
}
