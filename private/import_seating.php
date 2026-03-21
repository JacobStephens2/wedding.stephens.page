<?php
/**
 * Import initial seating chart assignments into the database.
 * Run once: php private/import_seating.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = getDbConnection();

// Define tables
$tables = [
    1  => ['name' => 'Head Table',                'notes' => 'Couple + bridal party & close Steubenville friends'],
    2  => ['name' => 'Stephens Immediate Family',  'notes' => "Groom's parents, siblings, aunts & uncles"],
    3  => ['name' => 'Stephens Young Families',     'notes' => 'Fleissner & Stephens kids'],
    4  => ['name' => 'Dill Family',                 'notes' => "Groom's maternal family"],
    5  => ['name' => 'Longua Immediate Family',     'notes' => "Bride's parents, siblings, grandma"],
    6  => ['name' => 'Extended Family',             'notes' => 'Longua, Philbin, Webber & Bair families'],
    7  => ['name' => "Bride's Friends",             'notes' => 'Steubenville & high school friends'],
    8  => ['name' => 'Coworkers & Friends',         'notes' => 'CHOP coworkers + a few St. AJ\'s friends'],
    9  => ['name' => "St. AJ's Parish Community",   'notes' => 'Clergy, musicians & altar servers'],
    10 => ['name' => "St. AJ's Couples",            'notes' => ''],
    11 => ['name' => "St. AJ's Friends",            'notes' => ''],
];

// Guest assignments: table_number => [ [first_name, last_name, seat_number], ... ]
$assignments = [
    1 => [
        ['Jacob', 'Stephens', 1],
        ['Melissa', 'Longua', 2],
        ['Rachel', 'Lavallee', 3],
        ['Erin', 'Balserak', 4],
        ['Holly', 'Tennyson', 5],
        ['Marissa', 'Bella', 6],
        ['Stephen', 'Lee', 7],
        ['Elizabeth', 'Hartmann', 8],
        ['Andrew', 'Doty', 9],
        ['Jacob', 'Hyman', 10],
    ],
    2 => [
        ['Mark', 'Stephens', 1],
        ['Melodee', 'Stephens', 2],
        ['Sarah', 'Hallowell', 3],
        ['Caleb', 'Hallowell', 4],
        ['Kurt', 'Stephens', 5],
        ['Rosi', 'Stephens', 6],
        ['Luz', 'Romero', 7],
        ['George', 'Stephens', 8],
        ['Carol', 'Stephens', 9],
        ['Chris', 'Reinert', 10],
    ],
    3 => [
        ['Tara', 'Fleissner', 1],
        ['Doug', 'Fleissner', 2],
        ['Scarlet', 'Fleissner', 3],
        ['Logan', 'Fleissner', 4],
        ['Parker', 'Fleissner', 5],
        ['Josh', 'Stephens', 6],
        ['Tyler', 'Stephens', 7],
        ['Hallie', 'Stephens', 8],
        ['Harper', 'Stephens', 9],
    ],
    4 => [
        ['Stephen', 'Dill', 1],
        ['Marge', 'Dill', 2],
        ['Jeff', 'Dill', 3],
        ['Heather', 'Dill', 4],
        ['Kristen', 'Levis', 5],
        ['John', 'Levis', 6],
        ['Robyn', 'Dill', 7],
        ['Matt', 'Palacz', 8],
    ],
    5 => [
        ['Ann', 'Longua', 1],
        ['John', 'Longua', 2],
        ['Lisa', 'Longua', 3],
        ['Matt', 'Longua', 4],
        ['Maria', 'Longua', 5],
        ['Rosalia', 'Longua', 6],
        ['Azélie', 'Longua', 7],
        ['Andrew', 'Longua', 8],
        ['Larry', 'Longua', 9],
        ['Linda', 'Longua', 10],
    ],
    6 => [
        ['Paul', 'Longua', 1],
        ['Patty', 'Longua', 2],
        ['Joan', 'Philbin', 3],
        ['Bill', 'Philbin', 4],
        ['Liam', 'Philbin', 5],
        ['Patrick', 'Philbin', 6],
        ['Chris', 'Webber', 7],
        ['Nilla', 'Webber', 8],
        ['Keith', 'Bair', 9],
        ['Margie', 'Bair', 10],
    ],
    7 => [
        ['Eleanor', 'Brambila', 1],
        ['Mary Emma', 'Brambila', 2],
        ['Salvador Leo', 'Brambila', 3],
        ['Pamela', 'Burton', 4],
        ['Clare', 'Martinez Morales', 5],
        ['Daniel', 'Martinez Morales', 6],
        ['Jennie', 'Holmstrom', 7],
        ['Bartłomiej', 'Miciuła', 8],
        ['Meagan', 'Lawrence', 9],
        ['Mikayla', 'Gornal', 10],
    ],
    8 => [
        ['Hannah', 'Bonelli', 1],
        ['Brennen', 'Covely', 2],
        ['David', 'Han', 3],
        ['Xinying', 'Hong', 4],
        ['Fan', 'Yi', 5],
        ['Diana', 'Vazquez', 6],
        ['Ejun', 'Hong', 7],
        ['Shuai', 'Yu', 8],
        ['Amy', 'Rogers', 9],
        ['Kathryn', 'Murphy', 10],
    ],
    9 => [
        ['Fr. Carlos', 'Keen', 1],
        ['Fr. Remi', 'Morales', 2],
        ['Michael', 'Gokie', 3],
        ['Anthony', 'Quinn', 4],
        ['Thibault', 'Vincent', 5],
        ['Mark', 'Odorizzi', 6],
        ['Michael', 'Lagrutta', 7],
        ['Joseph', 'Loeffler', 8],
        ['Stokely', 'Palmer Jr.', 9],
        ['Jay', 'Vargas', 10],
    ],
    10 => [
        ['Juan Marco', 'Alvarez', 1],
        ['Marcela', 'Figueroa', 2],
        ['Gonza', 'Lulika', 3],
        ['Briana', 'McBride', 4],
        ['Dan', 'Scofield', 5],
        ['Mica', 'Scofield', 6],
        ['Bryan', 'Wilson', 7],
        ['Evelyn', 'Okorie', 8],
        ['Marcos', 'Pereia', 9],
        ['Maria', 'Pereia', 10],
    ],
    11 => [
        ['Sarah', 'Busby', 1],
        ['Haila', 'Jiddou', 2],
        ['Quinn', 'Heiser', 3],
        ['Andrea', 'Garcia', 4],
        ['Logan', 'Moseley', 5],
        ['Tommy', 'Fruhauf', 6],
        ['Peter', 'Caulfield', 7],
        ['Milan', 'Varghese', 8],
    ],
];

// Insert tables
$insertTable = $pdo->prepare("INSERT INTO seating_tables (table_number, table_name, notes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE table_name = VALUES(table_name), notes = VALUES(notes)");
foreach ($tables as $num => $info) {
    $insertTable->execute([$num, $info['name'], $info['notes']]);
    echo "Table $num: {$info['name']}\n";
}

// Get table IDs
$tableIds = [];
$stmt = $pdo->query("SELECT id, table_number FROM seating_tables");
foreach ($stmt->fetchAll() as $row) {
    $tableIds[$row['table_number']] = $row['id'];
}

// Assign guests
$matched = 0;
$missed = [];
$updateGuest = $pdo->prepare("UPDATE guests SET seating_table_id = ?, seat_number = ? WHERE first_name = ? AND last_name = ?");

foreach ($assignments as $tableNum => $guests) {
    $tableId = $tableIds[$tableNum];
    foreach ($guests as $g) {
        [$first, $last, $seat] = $g;
        $updateGuest->execute([$tableId, $seat, $first, $last]);
        if ($updateGuest->rowCount() > 0) {
            $matched++;
        } else {
            // Try partial match for names with titles/nicknames
            $likeStmt = $pdo->prepare("UPDATE guests SET seating_table_id = ?, seat_number = ? WHERE first_name LIKE ? AND last_name LIKE ? AND seating_table_id IS NULL LIMIT 1");
            $likeStmt->execute([$tableId, $seat, "%$first%", "%$last%"]);
            if ($likeStmt->rowCount() > 0) {
                $matched++;
            } else {
                $missed[] = "$first $last (Table $tableNum)";
            }
        }
    }
}

echo "\nMatched: $matched guests\n";
if (!empty($missed)) {
    echo "Missed: " . count($missed) . " guests:\n";
    foreach ($missed as $m) echo "  - $m\n";
}

// Summary
$stmt = $pdo->query("SELECT COUNT(*) FROM guests WHERE seating_table_id IS NOT NULL");
echo "\nTotal guests with seating assignments: " . $stmt->fetchColumn() . "\n";
