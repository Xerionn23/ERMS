<?php
require __DIR__ . '/includes/db.php';
$pdo = db();
$rows = $pdo->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name IN ('railway','erms','erms_online','erms_cloud') ORDER BY schema_name")->fetchAll(PDO::FETCH_COLUMN);
echo 'SCHEMAS=' . implode(',', $rows) . PHP_EOL;
$counts = $pdo->query("SELECT table_schema, COUNT(*) c FROM information_schema.tables WHERE table_schema IN ('railway','erms','erms_online','erms_cloud') GROUP BY table_schema ORDER BY table_schema")->fetchAll(PDO::FETCH_ASSOC);
foreach ($counts as $r) {
    echo 'TABLES ' . $r['table_schema'] . '=' . $r['c'] . PHP_EOL;
}
