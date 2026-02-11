<?php
/**
 * Import guests from CSV into the guests database table.
 * Run from CLI: php private/import_guests.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$csvPath = __DIR__ . '/Guest List Feb 10 2026.csv';

if (!file_exists($csvPath)) {
    echo "ERROR: CSV file not found at: $csvPath\n";
    exit(1);
}

$pdo = getDbConnection();

// Check if guests already exist
$count = (int) $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn();
if ($count > 0) {
    echo "WARNING: guests table already has $count records.\n";
    echo "Do you want to clear existing data and re-import? (y/N): ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        echo "Aborted.\n";
        exit(0);
    }
    $pdo->exec("DELETE FROM guests");
    $pdo->exec("ALTER TABLE guests AUTO_INCREMENT = 1");
    echo "Cleared existing records.\n";
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    echo "ERROR: Could not open CSV file.\n";
    exit(1);
}

// Skip first two summary rows
fgetcsv($handle);
fgetcsv($handle);

// Read header row
$headers = fgetcsv($handle);
if (!$headers) {
    echo "ERROR: Could not read header row.\n";
    exit(1);
}

// Find column indices
$colMap = [];
foreach ($headers as $i => $header) {
    $colMap[trim($header)] = $i;
}

$requiredCols = ['First Name', 'Last Name', 'Group', 'id', 'Mailing Group'];
foreach ($requiredCols as $col) {
    if (!isset($colMap[$col])) {
        echo "ERROR: Required column '$col' not found in CSV headers.\n";
        echo "Available columns: " . implode(', ', $headers) . "\n";
        exit(1);
    }
}

$stmt = $pdo->prepare("
    INSERT INTO guests (first_name, last_name, group_name, guest_id, mailing_group)
    VALUES (:first_name, :last_name, :group_name, :guest_id, :mailing_group)
");

$imported = 0;
$skipped = 0;
$lineNum = 3; // We've already read 3 rows

while (($row = fgetcsv($handle)) !== false) {
    $lineNum++;
    
    $firstName = trim($row[$colMap['First Name']] ?? '');
    $lastName = trim($row[$colMap['Last Name']] ?? '');
    $groupName = trim($row[$colMap['Group']] ?? '');
    $guestId = trim($row[$colMap['id']] ?? '');
    $mailingGroup = trim($row[$colMap['Mailing Group']] ?? '');
    
    // Skip rows with no first name
    if (empty($firstName)) {
        echo "  SKIP line $lineNum: no first name\n";
        $skipped++;
        continue;
    }
    
    // Parse mailing group as integer, NULL if empty
    $mailingGroupInt = ($mailingGroup !== '' && is_numeric($mailingGroup)) ? (int)$mailingGroup : null;
    
    try {
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':group_name' => $groupName,
            ':guest_id' => $guestId,
            ':mailing_group' => $mailingGroupInt,
        ]);
        $imported++;
    } catch (Exception $e) {
        echo "  ERROR line $lineNum ($firstName $lastName): " . $e->getMessage() . "\n";
        $skipped++;
    }
}

fclose($handle);

echo "\nImport complete!\n";
echo "  Imported: $imported guests\n";
echo "  Skipped:  $skipped rows\n";

// Show summary by mailing group
$groups = $pdo->query("
    SELECT mailing_group, COUNT(*) as cnt 
    FROM guests 
    WHERE mailing_group IS NOT NULL 
    GROUP BY mailing_group 
    ORDER BY mailing_group
")->fetchAll();

echo "\nMailing groups: " . count($groups) . "\n";
echo "Total guests in groups: " . array_sum(array_column($groups, 'cnt')) . "\n";

$ungrouped = (int) $pdo->query("SELECT COUNT(*) FROM guests WHERE mailing_group IS NULL")->fetchColumn();
echo "Ungrouped guests: $ungrouped\n";
