<?php
$host = '127.0.0.1';
$db   = 'emsinfra-database';
$user = 'emsinfra';
$pass = 'Emsinfra@123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $outputFile = 'emsinfra_db_export_' . date('Y-m-d_H-i-s') . '.txt';
    $fp = fopen($outputFile, 'w');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        fwrite($fp, "\n=== Table: $table ===\n");
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
        foreach ($rows as $row) {
            fwrite($fp, json_encode($row, JSON_PRETTY_PRINT) . "\n");
        }
    }

    fclose($fp);
    echo "Export completed to $outputFile\n";
} catch (PDOException $e) {
    echo 'DB Error: ' . $e->getMessage();
}
