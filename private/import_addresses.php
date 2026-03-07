<?php
/**
 * Import mailing addresses from CSV into the mailing_addresses table.
 * Reads address data per mailing group, skipping entries with no address (^ or -).
 *
 * Usage: php private/import_addresses.php [--dry-run]
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$dryRun = in_array('--dry-run', $argv ?? []);

$csvPath = __DIR__ . '/sources/Guest List - Guest List.csv';
if (!file_exists($csvPath)) {
    echo "CSV file not found: $csvPath\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    echo "Could not open CSV file.\n";
    exit(1);
}

// Skip the first two header/summary rows
fgetcsv($handle);
fgetcsv($handle);

// Read the actual header row
$headers = fgetcsv($handle);
if (!$headers) {
    echo "Could not read header row.\n";
    exit(1);
}

// Map header names to indices
$colMap = [];
foreach ($headers as $i => $h) {
    $colMap[trim($h)] = $i;
}

$requiredCols = ['Mailing Group', 'Address 1', 'City', 'State', 'ZIP'];
foreach ($requiredCols as $col) {
    if (!isset($colMap[$col])) {
        echo "Missing required column: $col\n";
        exit(1);
    }
}

$mgCol = $colMap['Mailing Group'];
$addr1Col = $colMap['Address 1'];
$addr2Col = $colMap['Address 2'];
$cityCol = $colMap['City'];
$stateCol = $colMap['State'];
$zipCol = $colMap['ZIP'];
$countryCol = $colMap['Country'];

// Collect one address per mailing group (first non-^ non-- entry wins)
$addresses = [];

while (($row = fgetcsv($handle)) !== false) {
    $mailingGroup = trim($row[$mgCol] ?? '');
    if ($mailingGroup === '' || !is_numeric($mailingGroup)) {
        continue;
    }
    $mailingGroup = (int)$mailingGroup;

    // Skip if we already have this mailing group
    if (isset($addresses[$mailingGroup])) {
        continue;
    }

    $addr1 = trim($row[$addr1Col] ?? '');

    // Skip placeholder entries (^ means "same as above", - means no address)
    if ($addr1 === '^' || $addr1 === '-' || $addr1 === '' || $addr1 === 'N/A') {
        continue;
    }

    $addresses[$mailingGroup] = [
        'address_1' => $addr1,
        'address_2' => trim($row[$addr2Col] ?? '') ?: null,
        'city' => trim($row[$cityCol] ?? '') ?: null,
        'state' => trim($row[$stateCol] ?? '') ?: null,
        'zip' => trim($row[$zipCol] ?? '') ?: null,
        'country' => trim($row[$countryCol] ?? '') ?: null,
    ];
}

fclose($handle);

echo "Found addresses for " . count($addresses) . " mailing groups.\n";

if ($dryRun) {
    echo "\n[DRY RUN] Would insert the following:\n";
    foreach ($addresses as $mg => $addr) {
        $parts = array_filter([$addr['address_1'], $addr['address_2'], $addr['city'], $addr['state'], $addr['zip'], $addr['country']]);
        echo "  Mailing Group $mg: " . implode(', ', $parts) . "\n";
    }
    exit(0);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    INSERT INTO mailing_addresses (mailing_group, address_1, address_2, city, state, zip, country)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        address_1 = VALUES(address_1),
        address_2 = VALUES(address_2),
        city = VALUES(city),
        state = VALUES(state),
        zip = VALUES(zip),
        country = VALUES(country)
");

$inserted = 0;
foreach ($addresses as $mg => $addr) {
    $stmt->execute([
        $mg,
        $addr['address_1'],
        $addr['address_2'],
        $addr['city'],
        $addr['state'],
        $addr['zip'],
        $addr['country'],
    ]);
    $inserted++;
}

echo "Imported $inserted mailing addresses.\n";
