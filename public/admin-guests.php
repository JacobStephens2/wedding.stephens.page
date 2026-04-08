<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';
require_once __DIR__ . '/../private/admin_sample.php';

session_start();

$error = '';
$success = '';
$sampleMode = isAdminSampleMode();
$authenticated = $sampleMode;

// Check auth
if (!$sampleMode && isAdminAuthenticated()) {
    $authenticated = true;
}

// Handle login
if (!$sampleMode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['admin_login'])) {
    $password = trim($_POST['password'] ?? '');
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// Handle logout
if (!$sampleMode && isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-guests');
    exit;
}

// Handle rehearsal contacts CSV export
if (!$sampleMode && $authenticated && isset($_GET['export_rehearsal'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT first_name, last_name, group_name, mailing_group, phone, email,
                   address_1, address_2, city, state, zip, country
            FROM (
                SELECT g.first_name, g.last_name, g.group_name, g.mailing_group, g.phone, g.email,
                       ma.address_1, ma.address_2, ma.city, ma.state, ma.zip, ma.country
                FROM guests g
                LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
                WHERE g.rehearsal_invited = 1
                UNION ALL
                SELECT
                    CASE WHEN g.plus_one_name IS NOT NULL AND g.plus_one_name != ''
                         THEN SUBSTRING_INDEX(g.plus_one_name, ' ', 1)
                         ELSE '(Plus One)' END,
                    CASE WHEN g.plus_one_name IS NOT NULL AND g.plus_one_name != '' AND LOCATE(' ', g.plus_one_name) > 0
                         THEN SUBSTRING(g.plus_one_name, LOCATE(' ', g.plus_one_name) + 1)
                         ELSE '' END,
                    g.group_name, g.mailing_group, NULL, NULL,
                    ma.address_1, ma.address_2, ma.city, ma.state, ma.zip, ma.country
                FROM guests g
                LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
                WHERE g.has_plus_one = 1 AND g.plus_one_rehearsal_invited = 1
            ) combined
            ORDER BY group_name, last_name, first_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rehearsal-contacts.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['First Name', 'Last Name', 'Group', 'Mailing Group', 'Phone', 'Email', 'Address 1', 'Address 2', 'City', 'State', 'Zip', 'Country']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    } catch (Exception $e) {
        $error = 'Error exporting rehearsal contacts: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle dietary restrictions CSV export
if (!$sampleMode && $authenticated && isset($_GET['export_dietary'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT first_name, last_name, dietary
            FROM (
                SELECT g.first_name, g.last_name, g.dietary
                FROM guests g
                WHERE g.dietary IS NOT NULL AND g.dietary != ''
                UNION ALL
                SELECT
                    CASE WHEN g.plus_one_name IS NOT NULL AND g.plus_one_name != ''
                         THEN SUBSTRING_INDEX(g.plus_one_name, ' ', 1)
                         ELSE CONCAT('(+1 of ', g.first_name, ')') END,
                    CASE WHEN g.plus_one_name IS NOT NULL AND g.plus_one_name != '' AND LOCATE(' ', g.plus_one_name) > 0
                         THEN SUBSTRING(g.plus_one_name, LOCATE(' ', g.plus_one_name) + 1)
                         ELSE '' END,
                    g.plus_one_dietary
                FROM guests g
                WHERE g.has_plus_one = 1 AND g.plus_one_dietary IS NOT NULL AND g.plus_one_dietary != ''
            ) combined
            ORDER BY last_name, first_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dietary-restrictions.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['First Name', 'Last Name', 'Dietary Restriction']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    } catch (Exception $e) {
        $error = 'Error exporting dietary restrictions: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle pending RSVPs CSV export
if (!$sampleMode && $authenticated && isset($_GET['export_pending'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT g.first_name, g.last_name, g.group_name, g.phone, g.email,
                   ma.address_1, ma.address_2, ma.city, ma.state, ma.zip
            FROM guests g
            LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
            WHERE g.attending IS NULL
            ORDER BY g.group_name, g.last_name, g.first_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=\"pending-rsvps.csv\"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['First Name', 'Last Name', 'Group', 'Phone', 'Email', 'Address 1', 'Address 2', 'City', 'State', 'Zip']);
        foreach ($rows as $row) { fputcsv($out, $row); }
        fclose($out);
        exit;
    } catch (Exception $e) {
        $error = 'Error exporting pending contacts: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle full guest list CSV export
if (!$sampleMode && $authenticated && isset($_GET['export_all'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT g.first_name, g.last_name, g.group_name, g.mailing_group, g.email, g.phone,
                   g.attending, g.ceremony_attending, g.reception_attending,
                   g.has_plus_one, g.plus_one_name, g.plus_one_attending,
                   g.plus_one_ceremony_attending, g.plus_one_reception_attending,
                   g.dietary, g.plus_one_dietary, g.song_request, g.message, g.notes,
                   g.is_child, g.is_infant, g.age, g.plus_one_age, g.rehearsal_invited,
                   ma.address_1, ma.address_2, ma.city, ma.state, ma.zip, ma.country
            FROM guests g
            LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
            ORDER BY g.mailing_group, g.last_name, g.first_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=\"full-guest-list.csv\"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['First Name', 'Last Name', 'Group', 'Mailing Group', 'Email', 'Phone',
            'Attending', 'Ceremony', 'Reception', 'Has +1', '+1 Name', '+1 Attending',
            '+1 Ceremony', '+1 Reception', 'Dietary', '+1 Dietary', 'Song Request', 'Message', 'Notes',
            'Child', 'Infant', 'Age', '+1 Age', 'Rehearsal',
            'Address 1', 'Address 2', 'City', 'State', 'Zip', 'Country']);
        foreach ($rows as $row) { fputcsv($out, $row); }
        fclose($out);
        exit;
    } catch (Exception $e) {
        $error = 'Error exporting guest list: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle add guest
if (!$sampleMode && $authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_guest'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO guests (first_name, last_name, group_name, mailing_group, has_plus_one, rehearsal_invited, is_child, is_infant, age)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $mailingGroup = trim($_POST['mailing_group'] ?? '');
        $hasPlusOne = isset($_POST['has_plus_one']) && $_POST['has_plus_one'] === '1' ? 1 : 0;
        $rehearsalInvited = isset($_POST['rehearsal_invited']) && $_POST['rehearsal_invited'] === '1' ? 1 : 0;
        $isChild = isset($_POST['is_child']) && $_POST['is_child'] === '1' ? 1 : 0;
        $isInfant = isset($_POST['is_infant']) && $_POST['is_infant'] === '1' ? 1 : 0;
        $ageRaw = trim($_POST['age'] ?? '');
        $age = ($ageRaw !== '' && ctype_digit($ageRaw)) ? min(255, (int)$ageRaw) : null;
        $groupName = trim($_POST['group_name'] ?? '');
        $mailingGroupVal = $mailingGroup !== '' ? (int)$mailingGroup : null;
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            $groupName,
            $mailingGroupVal,
            $hasPlusOne,
            $rehearsalInvited,
            $isChild,
            $isInfant,
            $age,
        ]);

        // Insert extra guests in the same group
        $extraFirstNames = $_POST['extra_first_names'] ?? [];
        $extraLastNames = $_POST['extra_last_names'] ?? [];
        $extraIsChild = $_POST['extra_is_child'] ?? [];
        $extraIsInfant = $_POST['extra_is_infant'] ?? [];
        $extraAges = $_POST['extra_age'] ?? [];
        foreach ($extraFirstNames as $i => $firstName) {
            $extraFirst = trim($firstName ?? '');
            if ($extraFirst === '') continue;
            $extraLast = trim($extraLastNames[$i] ?? '');
            $extraChildVal = !empty($extraIsChild[$i]) ? 1 : 0;
            $extraInfantVal = !empty($extraIsInfant[$i]) ? 1 : 0;
            $extraAgeRaw = trim($extraAges[$i] ?? '');
            $extraAgeVal = ($extraAgeRaw !== '' && ctype_digit($extraAgeRaw)) ? min(255, (int)$extraAgeRaw) : null;
            $stmt->execute([
                $extraFirst,
                $extraLast,
                $groupName,
                $mailingGroupVal,
                0,
                0,
                $extraChildVal,
                $extraInfantVal,
                $extraAgeVal,
            ]);
        }

        header('Location: /admin-guests?added=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error adding guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle update guest
if (!$sampleMode && $authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guest'])) {
    try {
        $pdo = getDbConnection();
        $mailingGroup = trim($_POST['mailing_group'] ?? '');
        $ceremonyAttending = $_POST['ceremony_attending'] ?? '';
        $receptionAttending = $_POST['reception_attending'] ?? '';
        $hasPlusOne = isset($_POST['has_plus_one']) && $_POST['has_plus_one'] === '1' ? 1 : 0;
        
        // Derive overall attending from event-specific fields
        $ca = $ceremonyAttending !== '' ? $ceremonyAttending : null;
        $ra = $receptionAttending !== '' ? $receptionAttending : null;
        if ($ca === 'yes' || $ra === 'yes') {
            $attending = 'yes';
        } elseif ($ca === 'no' && $ra === 'no') {
            $attending = 'no';
        } else {
            $attending = null;
        }
        
        $phone = trim($_POST['phone'] ?? '');
        $rehearsalInvited = isset($_POST['rehearsal_invited']) && $_POST['rehearsal_invited'] === '1' ? 1 : 0;
        $plusOneRehearsalInvited = isset($_POST['plus_one_rehearsal_invited']) && $_POST['plus_one_rehearsal_invited'] === '1' ? 1 : 0;
        $isChild = isset($_POST['is_child']) && $_POST['is_child'] === '1' ? 1 : 0;
        $plusOneIsChild = isset($_POST['plus_one_is_child']) && $_POST['plus_one_is_child'] === '1' ? 1 : 0;
        $isInfant = isset($_POST['is_infant']) && $_POST['is_infant'] === '1' ? 1 : 0;
        $plusOneIsInfant = isset($_POST['plus_one_is_infant']) && $_POST['plus_one_is_infant'] === '1' ? 1 : 0;
        $ageRaw = trim($_POST['age'] ?? '');
        $age = ($ageRaw !== '' && ctype_digit($ageRaw)) ? min(255, (int)$ageRaw) : null;
        $plusOneAgeRaw = trim($_POST['plus_one_age'] ?? '');
        $plusOneAge = ($plusOneAgeRaw !== '' && ctype_digit($plusOneAgeRaw)) ? min(255, (int)$plusOneAgeRaw) : null;
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE guests
            SET first_name = ?, last_name = ?, group_name = ?, mailing_group = ?,
                attending = ?, ceremony_attending = ?, reception_attending = ?, has_plus_one = ?,
                phone = ?, rehearsal_invited = ?, plus_one_rehearsal_invited = ?,
                is_child = ?, plus_one_is_child = ?,
                is_infant = ?, plus_one_is_infant = ?,
                age = ?, plus_one_age = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            trim($_POST['group_name'] ?? ''),
            $mailingGroup !== '' ? (int)$mailingGroup : null,
            $attending,
            $ca,
            $ra,
            $hasPlusOne,
            $phone !== '' ? $phone : null,
            $rehearsalInvited,
            $plusOneRehearsalInvited,
            $isChild,
            $plusOneIsChild,
            $isInfant,
            $plusOneIsInfant,
            $age,
            $plusOneAge,
            $notes !== '' ? $notes : null,
            (int)$_POST['guest_id'],
        ]);

        // Update mailing address if a mailing group is set
        if ($mailingGroup !== '') {
            $mg = (int)$mailingGroup;
            $addr1 = trim($_POST['address_1'] ?? '');
            $addr2 = trim($_POST['address_2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $zip = trim($_POST['zip'] ?? '');
            $country = trim($_POST['country'] ?? '');

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
            $stmt->execute([
                $mg,
                $addr1 !== '' ? $addr1 : null,
                $addr2 !== '' ? $addr2 : null,
                $city !== '' ? $city : null,
                $state !== '' ? $state : null,
                $zip !== '' ? $zip : null,
                $country !== '' ? $country : null,
            ]);
        }
        header('Location: /admin-guests?updated=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error updating guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle delete guest
if (!$sampleMode && $authenticated && isset($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: /admin-guests?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error deleting guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle bulk action
if (!$sampleMode && $authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    try {
        $action = $_POST['bulk_action'];
        $guestIdsRaw = $_POST['guest_ids'] ?? '';
        $guestIds = array_filter(array_map('intval', explode(',', $guestIdsRaw)));

        if (!empty($guestIds)) {
            $allowedActions = [
                'mark_rehearsal' => ['rehearsal_invited', 1],
                'unmark_rehearsal' => ['rehearsal_invited', 0],
                'mark_child' => ['is_child', 1],
                'unmark_child' => ['is_child', 0],
            ];

            if (isset($allowedActions[$action])) {
                $pdo = getDbConnection();
                [$column, $value] = $allowedActions[$action];
                $placeholders = implode(',', array_fill(0, count($guestIds), '?'));
                $stmt = $pdo->prepare("UPDATE guests SET $column = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$value], $guestIds));
            }
        }

        header('Location: /admin-guests?updated=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error performing bulk action: ' . htmlspecialchars($e->getMessage());
    }
}


// Fetch guest for editing
$editGuest = null;
$editAddress = null;
if ($sampleMode && isset($_GET['edit'])) {
    foreach (getSampleGuestRecords() as $sampleGuest) {
        if ((int) $sampleGuest['id'] === (int) $_GET['edit']) {
            $editGuest = $sampleGuest;
            $editAddress = [
                'mailing_group' => $sampleGuest['mailing_group'],
                'address_1' => $sampleGuest['address_1'],
                'address_2' => $sampleGuest['address_2'],
                'city' => $sampleGuest['city'],
                'state' => $sampleGuest['state'],
                'zip' => $sampleGuest['zip'],
                'country' => $sampleGuest['country'],
            ];
            break;
        }
    }
} elseif ($authenticated && isset($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editGuest = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch mailing address for the guest's group
        if ($editGuest && !empty($editGuest['mailing_group'])) {
            $stmt = $pdo->prepare("SELECT * FROM mailing_addresses WHERE mailing_group = ?");
            $stmt->execute([(int)$editGuest['mailing_group']]);
            $editAddress = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = 'Error loading guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch guest for admin RSVP entry
$rsvpGuest = null;
if ($sampleMode && isset($_GET['rsvp'])) {
    foreach (getSampleGuestRecords() as $sampleGuest) {
        if ((int) $sampleGuest['id'] === (int) $_GET['rsvp']) {
            $rsvpGuest = $sampleGuest;
            break;
        }
    }
} elseif ($authenticated && isset($_GET['rsvp'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['rsvp']]);
        $rsvpGuest = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading guest for RSVP: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch all guests if authenticated
$guests = [];
$stats = ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0, 'ceremony' => 0, 'reception' => 0, 'ceremony_declined' => 0, 'reception_declined' => 0, 'rehearsal' => 0, 'reception_children' => 0, 'pending_children' => 0, 'reception_infants' => 0, 'pending_infants' => 0];
if ($sampleMode) {
    $search = trim($_GET['search'] ?? '');
    $groupFilter = trim($_GET['group_filter'] ?? '');
    $statusFilter = trim($_GET['status_filter'] ?? '');
    $sort = $_GET['sort'] ?? 'mailing_group';
    $order = $_GET['order'] ?? 'ASC';
    $sampleGuestsPage = getSampleGuestsPageData($search, $groupFilter, $statusFilter, $sort, $order);
    $guests = $sampleGuestsPage['guests'];
    $stats = $sampleGuestsPage['stats'];
    $householdStats = [
        'total_households' => $stats['total_households'],
        'households_attending' => $stats['households_attending'],
    ];
    $nextGroupNumber = $sampleGuestsPage['next_group_number'];
    $existingGroups = $sampleGuestsPage['existing_groups'];
    $rsvpTimeline = $sampleGuestsPage['rsvp_timeline'];
} elseif ($authenticated) {
    try {
        $pdo = getDbConnection();
        
        // Apply search filter if present
        $search = trim($_GET['search'] ?? '');
        $groupFilter = trim($_GET['group_filter'] ?? '');
        $statusFilter = trim($_GET['status_filter'] ?? '');
        
        // Sorting
        $sort = $_GET['sort'] ?? 'mailing_group';
        $order = $_GET['order'] ?? 'ASC';
        
        $allowedSorts = ['first_name', 'last_name', 'mailing_group', 'group_name', 'has_plus_one', 'rehearsal_invited', 'attending'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'mailing_group';
        }
        $order = ($order === 'DESC') ? 'DESC' : 'ASC';
        
        $where = [];
        $params = [];
        
        if ($search !== '') {
            $where[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR CONCAT(g.first_name, ' ', g.last_name) LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($groupFilter !== '') {
            $where[] = "g.mailing_group = ?";
            $params[] = (int)$groupFilter;
        }

        $allowedStatusFilters = ['attending', 'declined', 'pending', 'not_declined', 'ceremony_yes', 'ceremony_no', 'reception_yes', 'reception_no', 'rehearsal', 'adults', 'children', 'infants'];
        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatusFilters)) {
            switch ($statusFilter) {
                case 'attending':
                    $where[] = "g.attending = 'yes'";
                    break;
                case 'declined':
                    $where[] = "g.attending = 'no'";
                    break;
                case 'pending':
                    $where[] = "(g.attending IS NULL OR (g.has_plus_one = 1 AND g.plus_one_attending IS NULL))";
                    break;
                case 'not_declined':
                    $where[] = "(g.attending IS NULL OR g.attending = 'yes')";
                    break;
                case 'ceremony_yes':
                    $where[] = "g.ceremony_attending = 'yes'";
                    break;
                case 'ceremony_no':
                    $where[] = "g.ceremony_attending = 'no'";
                    break;
                case 'reception_yes':
                    $where[] = "g.reception_attending = 'yes'";
                    break;
                case 'reception_no':
                    $where[] = "g.reception_attending = 'no'";
                    break;
                case 'rehearsal':
                    $where[] = "g.rehearsal_invited = 1";
                    break;
                case 'adults':
                    $where[] = "((g.is_child = 0 AND g.is_infant = 0 AND g.reception_attending = 'yes') OR (g.has_plus_one = 1 AND g.plus_one_is_child = 0 AND g.plus_one_is_infant = 0 AND g.plus_one_reception_attending = 'yes'))";
                    break;
                case 'children':
                    $where[] = "((g.is_child = 1 AND g.reception_attending = 'yes') OR (g.has_plus_one = 1 AND g.plus_one_is_child = 1 AND g.plus_one_reception_attending = 'yes'))";
                    break;
                case 'infants':
                    $where[] = "((g.is_infant = 1 AND g.reception_attending = 'yes') OR (g.has_plus_one = 1 AND g.plus_one_is_infant = 1 AND g.plus_one_reception_attending = 'yes'))";
                    break;
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("
            SELECT g.*, ma.address_1, ma.address_2, ma.city, ma.state, ma.zip, ma.country
            FROM guests g
            LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
            $whereClause
            ORDER BY g.$sort $order, g.id ASC
        ");
        $stmt->execute($params);
        $rawGuests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Inject plus-one rows after their host guest
        $guests = [];
        foreach ($rawGuests as $guest) {
            $guests[] = $guest;
            if ($guest['has_plus_one']) {
                $plusOneName = trim($guest['plus_one_name'] ?? '');
                $poFirstName = $plusOneName ?: '(Plus One)';
                $poLastName = '';
                if ($plusOneName && strpos($plusOneName, ' ') !== false) {
                    $parts = explode(' ', $plusOneName, 2);
                    $poFirstName = $parts[0];
                    $poLastName = $parts[1];
                }

                // Check if plus-one passes the status filter
                $includeRow = true;
                if ($statusFilter !== '' && in_array($statusFilter, $allowedStatusFilters)) {
                    switch ($statusFilter) {
                        case 'attending':
                            $includeRow = ($guest['plus_one_attending'] ?? '') === 'yes';
                            break;
                        case 'declined':
                            $includeRow = ($guest['plus_one_attending'] ?? '') === 'no';
                            break;
                        case 'pending':
                            $includeRow = ($guest['plus_one_attending'] ?? null) === null;
                            break;
                        case 'not_declined':
                            $includeRow = ($guest['plus_one_attending'] ?? null) !== 'no';
                            break;
                        case 'ceremony_yes':
                            $includeRow = ($guest['plus_one_ceremony_attending'] ?? '') === 'yes';
                            break;
                        case 'ceremony_no':
                            $includeRow = ($guest['plus_one_ceremony_attending'] ?? '') === 'no';
                            break;
                        case 'reception_yes':
                            $includeRow = ($guest['plus_one_reception_attending'] ?? '') === 'yes';
                            break;
                        case 'reception_no':
                            $includeRow = ($guest['plus_one_reception_attending'] ?? '') === 'no';
                            break;
                        case 'rehearsal':
                            $includeRow = !empty($guest['plus_one_rehearsal_invited']);
                            break;
                        case 'adults':
                            $includeRow = empty($guest['plus_one_is_child']) && empty($guest['plus_one_is_infant']) && ($guest['plus_one_reception_attending'] ?? '') === 'yes';
                            break;
                        case 'children':
                            $includeRow = !empty($guest['plus_one_is_child']) && ($guest['plus_one_reception_attending'] ?? '') === 'yes';
                            break;
                        case 'infants':
                            $includeRow = !empty($guest['plus_one_is_infant']) && ($guest['plus_one_reception_attending'] ?? '') === 'yes';
                            break;
                    }
                }

                if ($includeRow) {
                    $guests[] = [
                        'id' => $guest['id'],
                        'first_name' => $poFirstName,
                        'last_name' => $poLastName,
                        'group_name' => $guest['group_name'],
                        'mailing_group' => $guest['mailing_group'],
                        'has_plus_one' => 0,
                        'attending' => $guest['plus_one_attending'],
                        'ceremony_attending' => $guest['plus_one_ceremony_attending'],
                        'reception_attending' => $guest['plus_one_reception_attending'],
                        'address_1' => $guest['address_1'],
                        'address_2' => $guest['address_2'],
                        'city' => $guest['city'],
                        'state' => $guest['state'],
                        'zip' => $guest['zip'],
                        'country' => $guest['country'],
                        'rehearsal_invited' => $guest['plus_one_rehearsal_invited'],
                        'is_child' => $guest['plus_one_is_child'],
                        'is_infant' => $guest['plus_one_is_infant'],
                        'age' => $guest['plus_one_age'] ?? null,
                        'is_plus_one' => true,
                    ];
                }
            }
        }

        // Stats (always from full table)
        // Counts are in "invites" (guests + granted plus-ones), per specification.
        $statsStmt = $pdo->query("
            SELECT 
                (
                    COUNT(*) + COALESCE(SUM(CASE WHEN has_plus_one = 1 THEN 1 ELSE 0 END), 0)
                ) as total,
                (
                    COALESCE(SUM(CASE WHEN attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as attending,
                (
                    COALESCE(SUM(CASE WHEN attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as declined,
                (
                    COALESCE(SUM(CASE WHEN attending IS NULL THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending IS NULL THEN 1 ELSE 0 END), 0)
                ) as pending,
                (
                    COALESCE(SUM(CASE WHEN ceremony_attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_ceremony_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as ceremony,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as reception,
                (
                    COALESCE(SUM(CASE WHEN ceremony_attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_ceremony_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as ceremony_declined,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as reception_declined,
                (
                    COALESCE(SUM(CASE WHEN rehearsal_invited = 1 THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_rehearsal_invited = 1 THEN 1 ELSE 0 END), 0)
                ) as rehearsal,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'yes' AND is_child = 1 THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'yes' AND plus_one_is_child = 1 THEN 1 ELSE 0 END), 0)
                ) as reception_children,
                (
                    COALESCE(SUM(CASE WHEN attending IS NULL AND is_child = 1 THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending IS NULL AND plus_one_is_child = 1 THEN 1 ELSE 0 END), 0)
                ) as pending_children,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'yes' AND is_infant = 1 THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'yes' AND plus_one_is_infant = 1 THEN 1 ELSE 0 END), 0)
                ) as reception_infants,
                (
                    COALESCE(SUM(CASE WHEN attending IS NULL AND is_infant = 1 THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending IS NULL AND plus_one_is_infant = 1 THEN 1 ELSE 0 END), 0)
                ) as pending_infants
            FROM guests
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        // Household (mailing group) stats
        $householdStmt = $pdo->query("
            SELECT
                COUNT(DISTINCT mailing_group) as total_households,
                COUNT(DISTINCT CASE WHEN attending = 'yes' THEN mailing_group END) as households_attending
            FROM guests
            WHERE mailing_group IS NOT NULL
        ");
        $householdStats = $householdStmt->fetch(PDO::FETCH_ASSOC);

        // Get next available mailing group number
        $nextGroupStmt = $pdo->query("SELECT COALESCE(MAX(mailing_group), 0) + 1 AS next_group FROM guests");
        $nextGroupNumber = $nextGroupStmt->fetch(PDO::FETCH_ASSOC)['next_group'];

        // Get existing groups for "Add guest to group" feature
        $groupsStmt = $pdo->query("SELECT DISTINCT mailing_group, group_name FROM guests WHERE mailing_group IS NOT NULL ORDER BY mailing_group ASC");
        $existingGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Age stats — count guests by age (primary + plus-ones who have an age set).
        // Each row is [age, reception_yes (0/1)] so we can show overall and reception-confirmed counts.
        $ageStmt = $pdo->query("
            SELECT age,
                   CASE WHEN reception_attending = 'yes' THEN 1 ELSE 0 END AS reception_yes
            FROM guests
            WHERE age IS NOT NULL
            UNION ALL
            SELECT plus_one_age AS age,
                   CASE WHEN plus_one_reception_attending = 'yes' THEN 1 ELSE 0 END AS reception_yes
            FROM guests
            WHERE has_plus_one = 1 AND plus_one_age IS NOT NULL
        ");
        $byAge = []; // age => ['total' => n, 'reception' => n]
        $totalCount = 0;
        $ageSum = 0;
        $minAge = null;
        $maxAge = null;
        foreach ($ageStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $a = (int)$r['age'];
            if (!isset($byAge[$a])) $byAge[$a] = ['total' => 0, 'reception' => 0];
            $byAge[$a]['total']++;
            if ((int)$r['reception_yes'] === 1) $byAge[$a]['reception']++;
            $totalCount++;
            $ageSum += $a;
            if ($minAge === null || $a < $minAge) $minAge = $a;
            if ($maxAge === null || $a > $maxAge) $maxAge = $a;
        }
        ksort($byAge);
        $missingAgeCount = (int)$pdo->query("
            SELECT
                (
                    COALESCE(SUM(CASE WHEN age IS NULL THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_age IS NULL THEN 1 ELSE 0 END), 0)
                ) AS missing
            FROM guests
        ")->fetch(PDO::FETCH_ASSOC)['missing'];
        $ageStats = [
            'count' => $totalCount,
            'avg' => $totalCount > 0 ? $ageSum / $totalCount : null,
            'min' => $minAge,
            'max' => $maxAge,
            'by_age' => $byAge,
            'missing' => $missingAgeCount,
        ];

        // Get RSVP submissions over time
        $timelineStmt = $pdo->query("
            SELECT DATE(rsvp_submitted_at) as rsvp_date, COUNT(*) as count
            FROM guests
            WHERE rsvp_submitted_at IS NOT NULL
            GROUP BY DATE(rsvp_submitted_at)
            ORDER BY rsvp_date ASC
        ");
        $rsvpTimeline = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading guests: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Manage Guests - Jacob & Melissa";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/includes/theme_init.php'; ?>
    <?php renderAdminSampleModeAssets(); ?>
    <link rel="stylesheet" href="/css/style.css?v=<?php
        $cssPath = __DIR__ . '/css/style.css';
        echo file_exists($cssPath) ? filemtime($cssPath) : time(); 
    ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&family=Crimson+Text:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .back-to-site {
            text-align: center;
            margin-bottom: 2rem;
        }
        .back-to-site a {
            color: var(--color-green);
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s;
        }
        .back-to-site a:hover {
            color: var(--color-gold);
            text-decoration: underline;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 1rem;
        }
        .logout-link a {
            color: var(--color-dark);
            text-decoration: none;
        }
        .logout-link a:hover {
            color: var(--color-green);
        }
        .stats-bar {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            font-family: 'Crimson Text', serif;
        }
        .stat-attending .stat-number { color: var(--color-green); }
        .stat-declined .stat-number { color: #dc3545; }
        .stat-pending .stat-number { color: var(--color-lavender); }
        .stat-link {
            text-decoration: none;
            display: block;
            cursor: pointer;
        }
        .stat-link:hover {
            opacity: 0.7;
        }
        
        .filters-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
        }
        .filters-bar input[type="text"],
        .filters-bar input[type="number"] {
            padding: 0.5rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
        }
        .filters-bar input[type="text"]:focus,
        .filters-bar input[type="number"]:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .btn-filter {
            padding: 0.5rem 1rem;
            background: var(--color-green);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-filter:hover { background: #6a7a54; }
        .btn-clear {
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-clear:hover { background: #5a6268; color: white; }
        
        .add-guest-form {
            position: relative;
            background: var(--color-surface);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
            margin-bottom: 2rem;
        }
        .add-guest-form h2 {
            color: var(--color-green);
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover { background-color: #5a6268; color: white; }
        
        .guests-table-container {
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
        }
        .guests-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Crimson Text', serif;
        }
        .guests-table th {
            background: var(--color-green);
            color: white;
            padding: 0;
            text-align: left;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: 400;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .guests-table th a {
            display: block;
            padding: 0.75rem 1rem;
            color: white;
            text-decoration: none;
            width: 100%;
            height: 100%;
        }
        .guests-table th a:hover {
            background: rgba(0,0,0,0.1);
        }
        .sort-indicator {
            font-size: 0.7rem;
            margin-left: 0.3rem;
            opacity: 0.7;
        }
        .guests-table td {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid var(--color-border);
            font-size: 1rem;
        }
        .guests-table tr:hover {
            background-color: var(--color-surface-alt);
        }
        .guests-table tr.group-start {
            border-top: 2px solid var(--color-green);
        }
        .guests-table tr.plus-one-row {
            font-style: italic;
            color: var(--color-text-secondary);
        }
        .rsvp-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .rsvp-attending { background: var(--color-alert-success-bg); color: var(--color-alert-success-text); }
        .rsvp-declined { background: var(--color-alert-error-bg); color: var(--color-alert-error-text); }
        .rsvp-pending { background: var(--color-light); color: var(--color-text-secondary); }
        
        .action-links {
            display: flex;
            gap: 0.5rem;
        }
        .action-links a {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: background 0.3s;
        }
        .action-links .edit-link {
            color: var(--color-green);
        }
        .action-links .edit-link:hover {
            background: rgba(127, 143, 101, 0.1);
        }
        .action-links .delete-link {
            color: #dc3545;
        }
        .action-links .delete-link:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
.btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-danger:hover { background: #c82333; color: white; }
        
        .guest-count-label {
            font-family: 'Crimson Text', serif;
            color: var(--color-text-secondary);
            margin-bottom: 1rem;
            display: block;
        }

        .bulk-action-bar {
            display: none;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding: 0.75rem 1.5rem;
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
            border-left: 4px solid var(--color-green);
        }
        .bulk-action-bar.visible {
            display: flex;
        }
        .bulk-selected-count {
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            color: var(--color-dark);
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .bulk-action-bar button {
            padding: 0.4rem 0.85rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            transition: background 0.2s, border-color 0.2s;
            background: var(--color-surface);
            color: var(--color-dark);
        }
        .bulk-action-bar button:hover {
            background: var(--color-green);
            color: white;
            border-color: var(--color-green);
        }
        .guests-table th.bulk-check-col,
        .guests-table td.bulk-check-col {
            width: 2rem;
            text-align: center;
            padding: 0.6rem 0.5rem;
        }
        .guests-table th.bulk-check-col {
            padding: 0.75rem 0.5rem;
        }
        
        .action-links .rsvp-link {
            color: #0d6efd;
        }
        .action-links .rsvp-link:hover {
            background: rgba(13, 110, 253, 0.1);
        }
        
        .admin-rsvp-form {
            background: var(--color-surface);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
            margin-bottom: 2rem;
        }
        .admin-rsvp-form h2 {
            color: var(--color-green);
            margin-bottom: 0.5rem;
        }
        .admin-rsvp-desc {
            font-family: 'Crimson Text', serif;
            color: var(--color-text-secondary);
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
        }
        .admin-rsvp-group-members {
            margin-bottom: 1.5rem;
        }
        .admin-rsvp-member-card {
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: var(--color-surface);
            transition: border-color 0.2s;
        }
        .admin-rsvp-member-card.ar-attending {
            border-color: var(--color-green);
            background: rgba(127, 143, 101, 0.03);
        }
        .admin-rsvp-member-card.ar-declined {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.02);
        }
        .ar-member-name {
            font-size: 1.15rem;
            color: var(--color-dark);
            display: block;
            margin-bottom: 0.6rem;
        }
        .ar-event-rows {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .ar-event-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ar-event-label {
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            color: var(--color-dark);
        }
        .ar-event-sublabel {
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }
        .ar-plus-one-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .ar-attending-toggle {
            display: flex;
            gap: 0;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--color-border);
        }
        .ar-attending-toggle button {
            padding: 0.4rem 1rem;
            border: none;
            background: var(--color-surface);
            cursor: pointer;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            transition: all 0.2s;
            color: var(--color-text-secondary);
        }
        .ar-attending-toggle button:not(:last-child) {
            border-right: 1px solid var(--color-border);
        }
        .ar-attending-toggle button.ar-active-yes {
            background: var(--color-green);
            color: white;
        }
        .ar-attending-toggle button.ar-active-no {
            background: #dc3545;
            color: white;
        }
        .ar-member-dietary {
            margin-top: 0.5rem;
        }
        .ar-member-dietary label {
            font-family: 'Crimson Text', serif;
            font-size: 0.95rem;
            color: var(--color-text-secondary);
            display: block;
            margin-bottom: 0.25rem;
        }
        .ar-member-dietary input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .ar-member-dietary input:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .ar-plus-one-card {
            border-style: dashed;
        }
        .ar-plus-one-label {
            font-style: italic;
            color: var(--color-dark);
            font-size: 0.9rem;
        }
        .ar-plus-one-details.ar-hidden {
            display: none;
        }
        .ar-plus-one-details {
            margin-top: 0.75rem;
        }
        .ar-plus-one-name-group {
            margin-bottom: 0.5rem;
        }
        .ar-plus-one-name-group label {
            font-family: 'Cinzel', serif;
            font-size: 0.95rem;
            color: var(--color-dark);
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }
        .ar-plus-one-name-group input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 2px solid var(--color-green);
            border-radius: 6px;
            font-family: 'Crimson Text', serif;
            font-size: 1.15rem;
            color: var(--color-dark);
            background: rgba(127, 143, 101, 0.04);
            box-sizing: border-box;
        }
        .ar-plus-one-name-group input:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(127, 143, 101, 0.25);
        }
        .admin-rsvp-form .form-group textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            min-height: 60px;
            box-sizing: border-box;
        }
        .admin-rsvp-form .form-group textarea:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .admin-rsvp-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .admin-rsvp-error {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: var(--color-alert-error-bg);
            color: var(--color-alert-error-text);
            border: 1px solid var(--color-alert-error-border);
            border-radius: 6px;
            display: none;
        }
        .admin-rsvp-success {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: var(--color-alert-success-bg);
            color: var(--color-alert-success-text);
            border: 1px solid var(--color-alert-success-border);
            border-radius: 6px;
            display: none;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <main class="page-container">
        <?php renderAdminSampleBanner('Guest Management Sample Mode'); ?>
        <div class="back-to-site">
            <a href="/">← Back to Main Site</a>
        </div>
        
        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Manage Guests</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/admin-guests">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-container">
                <div class="logout-link">
                    <a href="/admin-guests?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Manage Guests</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success"><p>Guest added successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success"><p>Guest updated successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success"><p>Guest deleted.</p></div>
                <?php endif; ?>
                
                <?php if ($rsvpGuest): ?>
                <!-- Admin RSVP Entry Form -->
                <div class="admin-rsvp-form" id="admin-rsvp-form">
                    <h2>Enter RSVP</h2>
                    <p class="admin-rsvp-desc">Entering RSVP for <strong><?php echo htmlspecialchars($rsvpGuest['first_name'] . ' ' . $rsvpGuest['last_name']); ?></strong>'s group (from mail-in card). No email required.</p>
                    
                    <div class="admin-rsvp-group-members" id="ar-group-members">
                        <p style="color:var(--color-text-secondary); font-family:'Crimson Text',serif;">Loading group members...</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-email">Email Address (Optional)</label>
                        <input type="email" id="ar-email" placeholder="Guest's email address, if provided...">
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-message">Message (Optional)</label>
                        <textarea id="ar-message" placeholder="Any message from the RSVP card..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-song">Song Request (Optional)</label>
                        <textarea id="ar-song" placeholder="Song request from the RSVP card..."></textarea>
                    </div>
                    
                    <div class="admin-rsvp-actions">
                        <button type="button" class="btn" id="ar-btn-submit">Submit RSVP</button>
                        <a href="/admin-guests" class="btn-secondary">Cancel</a>
                    </div>
                    
                    <div class="admin-rsvp-error" id="ar-error"></div>
                    <div class="admin-rsvp-success" id="ar-success"></div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const guestId = <?php echo (int)$rsvpGuest['id']; ?>;
                    const groupContainer = document.getElementById('ar-group-members');
                    const btnSubmit = document.getElementById('ar-btn-submit');
                    const errorDiv = document.getElementById('ar-error');
                    const successDiv = document.getElementById('ar-success');
                    let groupMembers = [];
                    
                    function escapeHtml(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }
                    
                    async function loadGroup() {
                        try {
                            const resp = await fetch('/api/guest-group?guest_id=' + guestId);
                            const data = await resp.json();
                            
                            if (data.error) {
                                groupContainer.innerHTML = '<p style="color:#dc3545;">Error: ' + escapeHtml(data.error) + '</p>';
                                return;
                            }
                            
                            groupMembers = data.group_members;
                            renderGroupForm();
                        } catch (err) {
                            groupContainer.innerHTML = '<p style="color:#dc3545;">Failed to load group. Please try again.</p>';
                        }
                    }
                    
                    function arEventToggleHtml(btnClass, currentVal) {
                        return '<div class="ar-attending-toggle">'
                             + '<button type="button" class="' + btnClass + (currentVal === 'yes' ? ' ar-active-yes' : '') + '" data-value="yes">Attending</button>'
                             + '<button type="button" class="' + btnClass + (currentVal === 'no' ? ' ar-active-no' : '') + '" data-value="no">Not Attending</button>'
                             + '</div>';
                    }
                    
                    function renderGroupForm() {
                        var html = '';
                        groupMembers.forEach(function(member) {
                            var name = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                            var curCeremony = member.ceremony_attending;
                            var curReception = member.reception_attending;
                            var curDietary = member.dietary || '';
                            
                            html += '<div class="admin-rsvp-member-card" data-member-id="' + member.id + '">'
                                 + '<span class="ar-member-name">' + escapeHtml(name) + '</span>'
                                 + '<div class="ar-event-rows">'
                                 + '<div class="ar-event-row">'
                                 + '<span class="ar-event-label">Ceremony <span class="ar-event-sublabel">(St. Agatha St. James)</span></span>'
                                 + arEventToggleHtml('ar-btn-ceremony', curCeremony)
                                 + '</div>'
                                 + '<div class="ar-event-row">'
                                 + '<span class="ar-event-label">Reception <span class="ar-event-sublabel">(Bala Golf Club)</span></span>'
                                 + arEventToggleHtml('ar-btn-reception', curReception)
                                 + '</div>'
                                 + '</div>'
                                 + '<div class="ar-member-dietary">'
                                 + '<label>Dietary restrictions or allergies</label>'
                                 + '<input type="text" data-dietary-for="' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(curDietary) + '">'
                                 + '</div>'
                                 + '</div>';
                            
                            if (parseInt(member.has_plus_one)) {
                                var poName = member.plus_one_name || '';
                                var poCeremony = member.plus_one_ceremony_attending;
                                var poReception = member.plus_one_reception_attending;
                                var poDietary = member.plus_one_dietary || '';
                                var notBringing = poCeremony === 'no' && poReception === 'no';
                                
                                html += '<div class="admin-rsvp-member-card ar-plus-one-card" data-plus-one-for="' + member.id + '">'
                                     + '<div class="ar-plus-one-header">'
                                     + '<span class="ar-member-name ar-plus-one-label">Guest of ' + escapeHtml(member.first_name) + '</span>'
                                     + '<div class="ar-attending-toggle">'
                                     + '<button type="button" class="ar-btn-po-toggle' + (!notBringing ? ' ar-active-yes' : '') + '" data-value="bringing">Bringing</button>'
                                     + '<button type="button" class="ar-btn-po-toggle' + (notBringing ? ' ar-active-no' : '') + '" data-value="not-bringing">Not Bringing</button>'
                                     + '</div>'
                                     + '</div>'
                                     + '<div class="ar-plus-one-details' + (notBringing ? ' ar-hidden' : '') + '">'
                                     + '<div class="ar-plus-one-name-group">'
                                     + '<label>Guest\'s Full Name</label>'
                                     + '<input type="text" data-po-name-for="' + member.id + '" placeholder="Enter guest\'s full name..." value="' + escapeHtml(poName) + '">'
                                     + '</div>'
                                     + '<div class="ar-event-rows">'
                                     + '<div class="ar-event-row">'
                                     + '<span class="ar-event-label">Ceremony <span class="ar-event-sublabel">(St. Agatha St. James)</span></span>'
                                     + arEventToggleHtml('ar-btn-po-ceremony', poCeremony)
                                     + '</div>'
                                     + '<div class="ar-event-row">'
                                     + '<span class="ar-event-label">Reception <span class="ar-event-sublabel">(Bala Golf Club)</span></span>'
                                     + arEventToggleHtml('ar-btn-po-reception', poReception)
                                     + '</div>'
                                     + '</div>'
                                     + '<div class="ar-member-dietary">'
                                     + '<label>Dietary restrictions or allergies</label>'
                                     + '<input type="text" data-po-dietary-for="' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(poDietary) + '">'
                                     + '</div>'
                                     + '</div>'
                                     + '</div>';
                            }
                        });
                        
                        groupContainer.innerHTML = html;
                        
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].email) {
                                document.getElementById('ar-email').value = groupMembers[i].email;
                                break;
                            }
                        }
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].message) {
                                document.getElementById('ar-message').value = groupMembers[i].message;
                                break;
                            }
                        }
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].song_request) {
                                document.getElementById('ar-song').value = groupMembers[i].song_request;
                                break;
                            }
                        }
                        
                        attachToggleHandlers();
                    }
                    
                    function attachToggleHandlers() {
                        groupContainer.querySelectorAll('.ar-btn-ceremony, .ar-btn-reception, .ar-btn-po-ceremony, .ar-btn-po-reception').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var toggle = this.closest('.ar-attending-toggle');
                                toggle.querySelectorAll('button').forEach(function(b) {
                                    b.classList.remove('ar-active-yes', 'ar-active-no');
                                });
                                this.classList.add(this.dataset.value === 'yes' ? 'ar-active-yes' : 'ar-active-no');
                                var card = this.closest('.admin-rsvp-member-card');
                                var anyYes = card.querySelectorAll('.ar-active-yes').length > 0;
                                card.classList.toggle('ar-attending', anyYes);
                                card.classList.toggle('ar-declined', !anyYes && card.querySelectorAll('.ar-active-no').length > 0);
                            });
                        });
                        
                        groupContainer.querySelectorAll('.ar-btn-po-toggle').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var card = this.closest('.ar-plus-one-card');
                                var toggle = this.closest('.ar-attending-toggle');
                                var details = card.querySelector('.ar-plus-one-details');
                                toggle.querySelectorAll('button').forEach(function(b) {
                                    b.classList.remove('ar-active-yes', 'ar-active-no');
                                });
                                if (this.dataset.value === 'bringing') {
                                    this.classList.add('ar-active-yes');
                                    if (details) details.classList.remove('ar-hidden');
                                    card.querySelectorAll('.ar-btn-po-ceremony.ar-active-no, .ar-btn-po-reception.ar-active-no').forEach(function(b) {
                                        b.classList.remove('ar-active-no');
                                    });
                                } else {
                                    this.classList.add('ar-active-no');
                                    if (details) details.classList.add('ar-hidden');
                                }
                            });
                        });
                    }
                    
                    async function submitAdminRsvp() {
                        errorDiv.style.display = 'none';
                        successDiv.style.display = 'none';
                        
                        var email = document.getElementById('ar-email').value.trim();
                        var message = document.getElementById('ar-message').value.trim();
                        var songRequest = document.getElementById('ar-song').value.trim();
                        
                        var guestData = [];
                        var hasResponse = false;
                        
                        groupContainer.querySelectorAll('.admin-rsvp-member-card:not(.ar-plus-one-card)').forEach(function(card) {
                            var memberId = parseInt(card.dataset.memberId);
                            var ceremonyBtn = card.querySelector('.ar-btn-ceremony.ar-active-yes, .ar-btn-ceremony.ar-active-no');
                            var receptionBtn = card.querySelector('.ar-btn-reception.ar-active-yes, .ar-btn-reception.ar-active-no');
                            var ceremonyAttending = ceremonyBtn ? ceremonyBtn.dataset.value : '';
                            var receptionAttending = receptionBtn ? receptionBtn.dataset.value : '';
                            var dietaryInput = card.querySelector('[data-dietary-for="' + memberId + '"]');
                            var dietary = dietaryInput ? dietaryInput.value.trim() : '';
                            
                            if (ceremonyAttending || receptionAttending) hasResponse = true;
                            
                            var entry = {
                                id: memberId,
                                ceremony_attending: ceremonyAttending,
                                reception_attending: receptionAttending,
                                dietary: dietary
                            };
                            
                            var poCard = groupContainer.querySelector('.ar-plus-one-card[data-plus-one-for="' + memberId + '"]');
                            if (poCard) {
                                var poToggleBtn = poCard.querySelector('.ar-btn-po-toggle.ar-active-no');
                                var notBringing = !!poToggleBtn;
                                var poCeremonyBtn = poCard.querySelector('.ar-btn-po-ceremony.ar-active-yes, .ar-btn-po-ceremony.ar-active-no');
                                var poReceptionBtn = poCard.querySelector('.ar-btn-po-reception.ar-active-yes, .ar-btn-po-reception.ar-active-no');
                                var poNameInput = poCard.querySelector('[data-po-name-for="' + memberId + '"]');
                                var poDietaryInput = poCard.querySelector('[data-po-dietary-for="' + memberId + '"]');
                                
                                entry.plus_one_ceremony_attending = notBringing ? 'no' : (poCeremonyBtn ? poCeremonyBtn.dataset.value : '');
                                entry.plus_one_reception_attending = notBringing ? 'no' : (poReceptionBtn ? poReceptionBtn.dataset.value : '');
                                entry.plus_one_name = poNameInput ? poNameInput.value.trim() : '';
                                entry.plus_one_dietary = poDietaryInput ? poDietaryInput.value.trim() : '';
                            }
                            
                            guestData.push(entry);
                        });
                        
                        if (!hasResponse) {
                            errorDiv.textContent = 'Please indicate ceremony or reception attendance for at least one guest.';
                            errorDiv.style.display = 'block';
                            return;
                        }
                        
                        btnSubmit.classList.add('loading');
                        btnSubmit.textContent = 'Submitting';
                        
                        try {
                            var resp = await fetch('/api/admin-submit-rsvp', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    guests: guestData,
                                    email: email,
                                    message: message,
                                    song_request: songRequest
                                })
                            });
                            
                            var data = await resp.json();
                            
                            if (data.success) {
                                successDiv.textContent = 'RSVP entered successfully! Attending: ' + data.attending_count + ', Declined: ' + data.declining_count;
                                successDiv.style.display = 'block';
                                btnSubmit.style.display = 'none';
                            } else {
                                errorDiv.textContent = data.error || 'An error occurred.';
                                errorDiv.style.display = 'block';
                            }
                        } catch (err) {
                            errorDiv.textContent = 'Network error. Please try again.';
                            errorDiv.style.display = 'block';
                        }
                        
                        btnSubmit.classList.remove('loading');
                        btnSubmit.textContent = 'Submit RSVP';
                    }
                    
                    btnSubmit.addEventListener('click', submitAdminRsvp);
                    loadGroup();
                });
                </script>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stat-item">
                        <a href="/admin-guests" class="stat-link">
                            <span class="stat-number"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">Total Invites (incl. +1s)</span>
                        </a>
                    </div>
                    <div class="stat-item stat-attending">
                        <a href="/admin-guests?status_filter=attending" class="stat-link">
                            <span class="stat-number"><?php echo $stats['attending']; ?></span>
                            <span class="stat-label">Attending Any</span>
                        </a>
                    </div>
                    <div class="stat-item stat-declined">
                        <a href="/admin-guests?status_filter=declined" class="stat-link">
                            <span class="stat-number"><?php echo $stats['declined']; ?></span>
                            <span class="stat-label">Declined All</span>
                        </a>
                    </div>
                    <div class="stat-item stat-pending">
                        <a href="/admin-guests?status_filter=pending" class="stat-link">
                            <span class="stat-number"><?php echo $stats['pending']; ?></span>
                            <span class="stat-label">Pending</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=not_declined" class="stat-link">
                            <span class="stat-number"><?php echo $stats['reception'] + $stats['pending']; ?></span>
                            <span class="stat-label">Max Reception if Pending Say Yes</span>
                        </a>
                        <?php
                            $maxAdults = ($stats['reception'] - $stats['reception_children'] - $stats['reception_infants']) + ($stats['pending'] - $stats['pending_children'] - $stats['pending_infants']);
                            $maxChildren = $stats['reception_children'] + $stats['pending_children'];
                            $maxInfants = $stats['reception_infants'] + $stats['pending_infants'];
                        ?>
                        <span class="stat-label" style="font-size: 0.75rem; color: var(--color-text-muted);"><?php echo $maxAdults; ?> adults, <?php echo $maxChildren; ?> children, <?php echo $maxInfants; ?> infants</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" style="color: var(--color-green);"><?php echo $householdStats['households_attending']; ?></span>
                        <span class="stat-label">Households Attending</span>
                        <span class="stat-label" style="font-size: 0.75rem; color: var(--color-text-muted);"><?php echo $householdStats['total_households']; ?> total</span>
                    </div>
                </div>
                <div class="stats-bar" style="margin-top: -1rem;">
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=ceremony_yes" class="stat-link">
                            <span class="stat-number" style="color: var(--color-green);"><?php echo $stats['ceremony']; ?></span>
                            <span class="stat-label">Ceremony</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=ceremony_no" class="stat-link">
                            <span class="stat-number" style="color: #dc3545;"><?php echo $stats['ceremony_declined']; ?></span>
                            <span class="stat-label">Declined Ceremony</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=reception_yes" class="stat-link">
                            <span class="stat-number" style="color: var(--color-green);"><?php echo $stats['reception']; ?></span>
                            <span class="stat-label">Reception</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=adults" class="stat-link">
                            <span class="stat-number" style="color: var(--color-green); font-size: 1.4rem;"><?php echo $stats['reception'] - $stats['reception_children'] - $stats['reception_infants']; ?></span>
                            <span class="stat-label">Adults</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=children" class="stat-link">
                            <span class="stat-number" style="color: var(--color-green); font-size: 1.4rem;"><?php echo $stats['reception_children']; ?></span>
                            <span class="stat-label">Children</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=infants" class="stat-link">
                            <span class="stat-number" style="color: var(--color-green); font-size: 1.4rem;"><?php echo $stats['reception_infants']; ?></span>
                            <span class="stat-label">Infants</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=reception_no" class="stat-link">
                            <span class="stat-number" style="color: #dc3545;"><?php echo $stats['reception_declined']; ?></span>
                            <span class="stat-label">Declined Reception</span>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="/admin-guests?status_filter=rehearsal" class="stat-link">
                            <span class="stat-number"><?php echo $stats['rehearsal']; ?></span>
                            <span class="stat-label">Rehearsal</span>
                        </a>
                    </div>
                    <?php if ($statusFilter !== ''): ?>
                        <div class="stat-item" style="align-self: center;">
                            <a href="/admin-guests" class="stat-link" style="font-size: 0.85rem; color: var(--color-text-secondary);">[Clear filter]</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add/Edit Guest Form -->
                <?php if (!$editGuest && !$rsvpGuest): ?>
                <button type="button" id="show-add-guest-btn" class="btn" style="margin-bottom: 1rem;">Add Guest</button>
                <?php endif; ?>
                <div class="add-guest-form" <?php if (!$editGuest || $rsvpGuest): ?>style="display:none;"<?php endif; ?>>
                    <?php if (!$editGuest): ?>
                    <button type="button" id="close-add-guest-btn" style="position:absolute; top:0.5rem; right:0.75rem; background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--color-text-muted); line-height:1;" title="Close">&times;</button>
                    <?php endif; ?>
                    <h2><?php echo $editGuest ? 'Edit Guest' : 'Add Guest'; ?></h2>
                    <form method="POST" action="/admin-guests">
                        <?php if ($editGuest): ?>
                            <input type="hidden" name="update_guest" value="1">
                            <input type="hidden" name="guest_id" value="<?php echo htmlspecialchars($editGuest['id']); ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_guest" value="1">
                        <?php endif; ?>
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo htmlspecialchars($editGuest['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($editGuest['last_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="group_name">Group Name</label>
                                <input type="text" id="group_name" name="group_name" list="group_name_options"
                                       value="<?php echo htmlspecialchars($editGuest['group_name'] ?? ''); ?>">
                                <datalist id="group_name_options">
                                    <?php
                                    $seenNames = [];
                                    foreach ($existingGroups ?? [] as $grp) {
                                        $name = $grp['group_name'];
                                        if ($name !== '' && $name !== null && !isset($seenNames[$name])) {
                                            $seenNames[$name] = true;
                                            echo '<option value="' . htmlspecialchars($name) . '">';
                                        }
                                    }
                                    ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="mailing_group">Mailing Group #</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" id="mailing_group" name="mailing_group"
                                       value="<?php echo htmlspecialchars($editGuest ? ($editGuest['mailing_group'] ?? '') : ($nextGroupNumber ?? '')); ?>">
                            </div>
                            <?php if (!$editGuest): ?>
                            <div class="form-group" style="padding-top:1.8rem;">
                                <button type="button" id="add-to-group-btn" class="btn-secondary" style="white-space:nowrap;">Add guest to group</button>
                            </div>
                            <?php endif; ?>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:120px; padding-top:1.8rem;">
                                <input type="checkbox" id="has_plus_one" name="has_plus_one" value="1"
                                       <?php echo (!empty($editGuest['has_plus_one'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="has_plus_one" style="margin:0; cursor:pointer;">Plus One</label>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:140px; padding-top:1.8rem;">
                                <input type="checkbox" id="rehearsal_invited" name="rehearsal_invited" value="1"
                                       <?php echo (!empty($editGuest['rehearsal_invited'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="rehearsal_invited" style="margin:0; cursor:pointer;">Rehearsal</label>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:100px; padding-top:1.8rem;">
                                <input type="checkbox" id="is_child" name="is_child" value="1"
                                       <?php echo (!empty($editGuest['is_child'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="is_child" style="margin:0; cursor:pointer;">Child</label>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:100px; padding-top:1.8rem;">
                                <input type="checkbox" id="is_infant" name="is_infant" value="1"
                                       <?php echo (!empty($editGuest['is_infant'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="is_infant" style="margin:0; cursor:pointer;">Infant</label>
                            </div>
                            <div class="form-group" style="min-width:80px; max-width:90px;">
                                <label for="age">Age</label>
                                <input type="number" id="age" name="age" min="0" max="120" step="1" inputmode="numeric"
                                       value="<?php echo htmlspecialchars((isset($editGuest['age']) && $editGuest['age'] !== null) ? $editGuest['age'] : ''); ?>">
                            </div>
                            <?php if ($editGuest && !empty($editGuest['has_plus_one'])): ?>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:160px; padding-top:1.8rem;">
                                <input type="checkbox" id="plus_one_rehearsal_invited" name="plus_one_rehearsal_invited" value="1"
                                       <?php echo (!empty($editGuest['plus_one_rehearsal_invited'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="plus_one_rehearsal_invited" style="margin:0; cursor:pointer;">+1 Rehearsal</label>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:120px; padding-top:1.8rem;">
                                <input type="checkbox" id="plus_one_is_child" name="plus_one_is_child" value="1"
                                       <?php echo (!empty($editGuest['plus_one_is_child'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="plus_one_is_child" style="margin:0; cursor:pointer;">+1 Child</label>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:120px; padding-top:1.8rem;">
                                <input type="checkbox" id="plus_one_is_infant" name="plus_one_is_infant" value="1"
                                       <?php echo (!empty($editGuest['plus_one_is_infant'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="plus_one_is_infant" style="margin:0; cursor:pointer;">+1 Infant</label>
                            </div>
                            <div class="form-group" style="min-width:90px; max-width:100px;">
                                <label for="plus_one_age">+1 Age</label>
                                <input type="number" id="plus_one_age" name="plus_one_age" min="0" max="120" step="1" inputmode="numeric"
                                       value="<?php echo htmlspecialchars((isset($editGuest['plus_one_age']) && $editGuest['plus_one_age'] !== null) ? $editGuest['plus_one_age'] : ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if ($editGuest): ?>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($editGuest['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="ceremony_attending">Ceremony</label>
                                <select id="ceremony_attending" name="ceremony_attending">
                                    <option value="" <?php echo ($editGuest['ceremony_attending'] === null) ? 'selected' : ''; ?>>Pending</option>
                                    <option value="yes" <?php echo ($editGuest['ceremony_attending'] === 'yes') ? 'selected' : ''; ?>>Attending</option>
                                    <option value="no" <?php echo ($editGuest['ceremony_attending'] === 'no') ? 'selected' : ''; ?>>Declined</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reception_attending">Reception</label>
                                <select id="reception_attending" name="reception_attending">
                                    <option value="" <?php echo ($editGuest['reception_attending'] === null) ? 'selected' : ''; ?>>Pending</option>
                                    <option value="yes" <?php echo ($editGuest['reception_attending'] === 'yes') ? 'selected' : ''; ?>>Attending</option>
                                    <option value="no" <?php echo ($editGuest['reception_attending'] === 'no') ? 'selected' : ''; ?>>Declined</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="form-group" style="flex-basis:100%;">
                                <label for="notes">Admin Notes</label>
                                <textarea id="notes" name="notes" rows="2" style="width:100%; padding:0.5rem; border:1px solid var(--color-border); border-radius:4px; font-family:'Crimson Text',serif; font-size:1rem;"><?php echo htmlspecialchars($editGuest['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <?php if ($editGuest): ?>
                        <h3 style="margin: 1rem 0 0.5rem; font-size: 1rem; color: var(--color-text-secondary);">Mailing Address (Group #<?php echo htmlspecialchars($editGuest['mailing_group'] ?? 'N/A'); ?>)</h3>
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label for="address_1">Address Line 1</label>
                                <input type="text" id="address_1" name="address_1"
                                       value="<?php echo htmlspecialchars($editAddress['address_1'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="address_2">Address Line 2</label>
                                <input type="text" id="address_2" name="address_2"
                                       value="<?php echo htmlspecialchars($editAddress['address_2'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city"
                                       value="<?php echo htmlspecialchars($editAddress['city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state"
                                       value="<?php echo htmlspecialchars($editAddress['state'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="zip">Zip</label>
                                <input type="text" id="zip" name="zip"
                                       value="<?php echo htmlspecialchars($editAddress['zip'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country"
                                       value="<?php echo htmlspecialchars($editAddress['country'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!$editGuest): ?>
                        <div id="extra-guests-container"></div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn" <?php if (!$editGuest): ?>id="add-guest-submit"<?php endif; ?>><?php echo $editGuest ? 'Update Guest' : 'Add Guest'; ?></button>
                            <?php if ($editGuest): ?>
                                <a href="/admin-guests" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <script>
                (function() {
                    var btn = document.getElementById('show-add-guest-btn');
                    if (!btn) return;
                    var form = document.querySelector('.add-guest-form');
                    var closeBtn = document.getElementById('close-add-guest-btn');
                    btn.addEventListener('click', function() {
                        form.style.display = '';
                        btn.style.display = 'none';
                    });
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function() {
                            form.style.display = 'none';
                            btn.style.display = '';
                        });
                    }
                })();
                </script>

                <!-- Search/Filter -->
                <form method="GET" action="/admin-guests" class="filters-bar">
                    <input type="text" name="search" id="guest-search-input" placeholder="Search name..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="group_filter" placeholder="Group #" style="width: 100px;"
                           value="<?php echo htmlspecialchars($_GET['group_filter'] ?? ''); ?>">
                    <button type="submit" class="btn-filter">Search</button>
                    <a href="/admin-guests" class="btn-clear">Clear</a>
                </form>
                
                <!-- Dietary Restrictions View -->
                <button type="button" id="dietary-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Dietary Restrictions</button>
                <div id="dietary-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h3 style="margin: 0;">Dietary Restrictions</h3>
                        <a href="/admin-guests?export_dietary=1" class="btn-secondary" style="text-decoration: none; font-size: 0.9rem;">Export Dietary Restrictions</a>
                    </div>
                    <?php
                    $dietaryEntries = [];
                    foreach ($guests as $g) {
                        if (!empty(trim($g['dietary'] ?? ''))) {
                            $dietaryEntries[] = [
                                'name' => htmlspecialchars($g['first_name'] . ' ' . $g['last_name']),
                                'dietary' => htmlspecialchars($g['dietary']),
                            ];
                        }
                        if ($g['has_plus_one'] && !empty(trim($g['plus_one_dietary'] ?? ''))) {
                            $poName = !empty(trim($g['plus_one_name'] ?? ''))
                                ? htmlspecialchars($g['plus_one_name'])
                                : htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) . "'s +1";
                            $dietaryEntries[] = [
                                'name' => $poName,
                                'dietary' => htmlspecialchars($g['plus_one_dietary']),
                            ];
                        }
                    }
                    if (empty($dietaryEntries)): ?>
                        <p style="color: var(--color-text-secondary);">No dietary restrictions have been submitted.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Guest</th>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Dietary Restriction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dietaryEntries as $entry): ?>
                                    <tr>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['name']; ?></td>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['dietary']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('dietary-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('dietary-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Dietary Restrictions' : 'Hide Dietary Restrictions';
                });
                </script>

                <!-- Song Requests View -->
                <button type="button" id="songs-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Song Requests</button>
                <div id="songs-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Song Requests</h3>
                    <?php
                    $songEntries = [];
                    $seenGroups = [];
                    foreach ($guests as $g) {
                        if (!empty(trim($g['song_request'] ?? ''))) {
                            $groupKey = $g['mailing_group'] ?? $g['id'];
                            if (isset($seenGroups[$groupKey])) continue;
                            $seenGroups[$groupKey] = true;
                            $songEntries[] = [
                                'name' => htmlspecialchars($g['first_name'] . ' ' . $g['last_name']),
                                'song' => htmlspecialchars($g['song_request']),
                            ];
                        }
                    }
                    if (empty($songEntries)): ?>
                        <p style="color: var(--color-text-secondary);">No song requests have been submitted.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Guest</th>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Song Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($songEntries as $entry): ?>
                                    <tr>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['name']; ?></td>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['song']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('songs-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('songs-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Song Requests' : 'Hide Song Requests';
                });
                </script>

                <!-- Age Stats View -->
                <?php $hasAgeData = isset($ageStats) && $ageStats['count'] > 0; ?>
                <button type="button" id="age-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Age Breakdown</button>
                <div id="age-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Guests by Age</h3>
                    <?php if (!$hasAgeData): ?>
                        <p style="color: var(--color-text-secondary);">No ages have been entered yet. Add ages on the Edit Guest form to see a breakdown here.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1rem; font-size: 0.95rem;">
                            <div><strong>Average:</strong> <?php echo number_format($ageStats['avg'], 1); ?></div>
                            <div><strong>Range:</strong> <?php echo (int)$ageStats['min']; ?>–<?php echo (int)$ageStats['max']; ?></div>
                            <div><strong>With age set:</strong> <?php echo (int)$ageStats['count']; ?></div>
                            <?php if ((int)$ageStats['missing'] > 0): ?>
                                <div style="color: var(--color-text-muted);"><strong>Missing age:</strong> <?php echo (int)$ageStats['missing']; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php
                            $maxBucket = 0;
                            foreach ($ageStats['by_age'] as $bucket) {
                                if ($bucket['total'] > $maxBucket) $maxBucket = $bucket['total'];
                            }
                        ?>
                        <table style="width: 100%; border-collapse: collapse; max-width: 540px;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.4rem 0.5rem; border-bottom: 2px solid var(--color-border);">Age</th>
                                    <th style="text-align: right; padding: 0.4rem 0.5rem; border-bottom: 2px solid var(--color-border);">Total</th>
                                    <th style="text-align: right; padding: 0.4rem 0.5rem; border-bottom: 2px solid var(--color-border);">Coming</th>
                                    <th style="padding: 0.4rem 0.5rem; border-bottom: 2px solid var(--color-border);"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ageStats['by_age'] as $age => $bucket):
                                    $barPct = $maxBucket > 0 ? ($bucket['total'] / $maxBucket) * 100 : 0;
                                ?>
                                    <tr>
                                        <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo (int)$age; ?></td>
                                        <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--color-border); text-align: right;"><?php echo (int)$bucket['total']; ?></td>
                                        <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--color-border); text-align: right; color: var(--color-green);"><?php echo (int)$bucket['reception']; ?></td>
                                        <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--color-border); width: 50%;">
                                            <div style="background: var(--color-gold); height: 0.6rem; width: <?php echo $barPct; ?>%; border-radius: 2px;"></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="color: var(--color-text-muted); font-size: 0.8rem; margin-top: 0.75rem;">"Coming" counts only guests whose reception RSVP is yes. "Total" counts everyone with an age on file regardless of RSVP status.</p>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('age-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('age-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Age Breakdown' : 'Hide Age Breakdown';
                });
                </script>

                <!-- Guest Messages View -->
                <button type="button" id="messages-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Guest Messages</button>
                <div id="messages-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Guest Messages</h3>
                    <?php
                    $messageEntries = [];
                    $seenMessageGroups = [];
                    foreach ($guests as $g) {
                        if (!empty(trim($g['message'] ?? ''))) {
                            $groupKey = $g['mailing_group'] ?? $g['id'];
                            if (isset($seenMessageGroups[$groupKey])) continue;
                            $seenMessageGroups[$groupKey] = true;
                            $messageEntries[] = [
                                'name' => htmlspecialchars($g['first_name'] . ' ' . $g['last_name']),
                                'message' => htmlspecialchars($g['message']),
                            ];
                        }
                    }
                    if (empty($messageEntries)): ?>
                        <p style="color: var(--color-text-secondary);">No messages have been submitted.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Guest</th>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid var(--color-border);">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messageEntries as $entry): ?>
                                    <tr>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['name']; ?></td>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--color-border);"><?php echo $entry['message']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('messages-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('messages-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Guest Messages' : 'Hide Guest Messages';
                });
                </script>

                <!-- Export Rehearsal Contacts -->
                <a href="/admin-guests?export_rehearsal=1" class="btn-filter" style="margin-bottom: 1rem; display: inline-block; text-decoration: none;">Export Rehearsal Contacts</a>
                <a href="/admin-guests?export_pending=1" class="btn-filter" style="margin-bottom: 1rem; display: inline-block; text-decoration: none;">Export Pending RSVPs</a>
                <a href="/admin-guests?export_all=1" class="btn-filter" style="margin-bottom: 1rem; display: inline-block; text-decoration: none;">Export Full Guest List</a>

                <!-- Reception Guests by Group View -->
                <button type="button" id="groups-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Reception Guests by Group</button>
                <div id="groups-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Reception Guests by Group</h3>
                    <?php
                    $groupCounts = [];
                    foreach ($guests as $g) {
                        if (!empty($g['is_plus_one'])) continue;
                        $groupName = !empty($g['group_name']) ? $g['group_name'] : '(No group)';
                        $key = $groupName;
                        if (!isset($groupCounts[$key])) {
                            $groupCounts[$key] = 0;
                        }
                        if (($g['reception_attending'] ?? '') !== 'no') {
                            $groupCounts[$key]++;
                        }
                        if (!empty($g['has_plus_one']) && ($g['plus_one_reception_attending'] ?? '') !== 'no') {
                            $groupCounts[$key]++;
                        }
                    }
                    ksort($groupCounts, SORT_NATURAL | SORT_FLAG_CASE);
                    $maxCount = !empty($groupCounts) ? max($groupCounts) : 1;
                    if (empty($groupCounts)): ?>
                        <p style="color: var(--color-text-secondary);">No guests found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <div style="min-width: <?php echo max(count($groupCounts) * 60, 300); ?>px; padding: 0 0.5rem;">
                                <div style="display: flex; align-items: flex-end; gap: 4px; height: 200px; border-bottom: 2px solid var(--color-border);">
                                    <?php foreach ($groupCounts as $name => $count):
                                        $pct = ($count / $maxCount) * 100;
                                    ?>
                                    <div class="chart-bar-col" data-group-name="<?php echo htmlspecialchars($name); ?>" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-width: 50px; height: 100%; cursor: pointer;">
                                        <span style="font-size: 0.8rem; font-weight: bold; margin-bottom: 2px;"><?php echo $count; ?></span>
                                        <div style="width: 80%; background: var(--color-green); border-radius: 4px 4px 0 0; height: <?php echo max($pct, 2); ?>%;" title="<?php echo htmlspecialchars($name) . ': ' . $count; ?>"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display: flex; gap: 4px;">
                                    <?php foreach ($groupCounts as $name => $count): ?>
                                    <div style="flex: 1; min-width: 50px; text-align: center;">
                                        <span style="font-size: 0.7rem; display: inline-block; margin-top: 4px; writing-mode: vertical-lr; transform: rotate(180deg); max-height: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                        $jTotal = 0; $mTotal = 0; $usTotal = 0;
                        foreach ($groupCounts as $name => $count) {
                            if (strncasecmp($name, 'J', 1) === 0) $jTotal += $count;
                            if (strncasecmp($name, 'M', 1) === 0) $mTotal += $count;
                            if (strncasecmp($name, 'Us', 2) === 0) $usTotal += $count;
                        }
                        ?>
                        <div style="display: flex; gap: 1.5rem; margin-top: 1rem; padding: 0.75rem; background: var(--color-surface); border-radius: 6px; border: 1px solid var(--color-border);">
                            <div style="text-align: center;">
                                <div style="font-size: 1.4rem; font-weight: bold; color: var(--color-green);"><?php echo $jTotal; ?></div>
                                <div style="font-size: 0.8rem; color: var(--color-text-secondary);">J Group Guests</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 1.4rem; font-weight: bold; color: var(--color-green);"><?php echo $mTotal; ?></div>
                                <div style="font-size: 0.8rem; color: var(--color-text-secondary);">M Group Guests</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 1.4rem; font-weight: bold; color: var(--color-green);"><?php echo $usTotal; ?></div>
                                <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Us Group Guests</div>
                            </div>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--color-text-muted); margin: 0.75rem 0 0; font-style: italic;">Note: Guests who have declined the reception are excluded from these counts.</p>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('groups-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('groups-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Reception Guests by Group' : 'Hide Reception Guests by Group';
                });
                </script>

                <!-- RSVP Timeline View -->
                <button type="button" id="timeline-toggle" class="btn-filter" style="margin-bottom: 1rem;">View RSVP Timeline</button>
                <div id="timeline-panel" style="display: none; margin-bottom: 1.5rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">RSVP Timeline</h3>
                    <?php
                    $totalGuests = (int)($stats['total'] ?? 0);
                    $totalResponded = 0;
                    foreach ($rsvpTimeline as $day) {
                        $totalResponded += (int)$day['count'];
                    }
                    $responsePct = $totalGuests > 0 ? round(($totalResponded / $totalGuests) * 100, 1) : 0;
                    ?>
                    <div style="display: flex; gap: 1.5rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--color-surface); border-radius: 6px; border: 1px solid var(--color-border);">
                        <div style="text-align: center;">
                            <div style="font-size: 1.4rem; font-weight: bold; color: var(--color-green);"><?php echo $totalResponded; ?> of <?php echo $totalGuests; ?></div>
                            <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Guests Responded (<?php echo $responsePct; ?>%)</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.4rem; font-weight: bold; color: var(--color-green);"><?php echo count($rsvpTimeline); ?></div>
                            <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Days with Responses</div>
                        </div>
                    </div>
                    <?php if (empty($rsvpTimeline)): ?>
                        <p style="color: var(--color-text-secondary);">No RSVP responses have been submitted yet.</p>
                    <?php else:
                        $maxDayCount = max(array_column($rsvpTimeline, 'count'));
                    ?>
                        <div style="overflow-x: auto;">
                            <div style="min-width: <?php echo max(count($rsvpTimeline) * 60, 300); ?>px; padding: 0 0.5rem;">
                                <div style="display: flex; align-items: flex-end; gap: 4px; height: 200px; border-bottom: 2px solid var(--color-border);">
                                    <?php foreach ($rsvpTimeline as $day):
                                        $pct = ((int)$day['count'] / $maxDayCount) * 100;
                                    ?>
                                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-width: 50px; height: 100%;">
                                        <span style="font-size: 0.8rem; font-weight: bold; margin-bottom: 2px;"><?php echo (int)$day['count']; ?></span>
                                        <div style="width: 80%; background: var(--color-green); border-radius: 4px 4px 0 0; height: <?php echo max($pct, 2); ?>%;" title="<?php echo htmlspecialchars($day['rsvp_date']) . ': ' . (int)$day['count']; ?> responses"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display: flex; gap: 4px;">
                                    <?php foreach ($rsvpTimeline as $day):
                                        $dateObj = new DateTime($day['rsvp_date']);
                                        $formatted = $dateObj->format('n/j');
                                    ?>
                                    <div style="flex: 1; min-width: 50px; text-align: center;">
                                        <span style="font-size: 0.7rem; display: inline-block; margin-top: 4px; writing-mode: vertical-lr; transform: rotate(180deg); max-height: 60px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($day['rsvp_date']); ?>"><?php echo $formatted; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                        // Show cumulative running total
                        $cumulative = 0;
                        ?>
                        <div style="margin-top: 1rem; padding: 0.75rem; background: var(--color-surface); border-radius: 6px; border: 1px solid var(--color-border);">
                            <div style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.5rem;">Cumulative Responses</div>
                            <div style="display: flex; align-items: flex-end; gap: 4px; height: 120px; border-bottom: 2px solid var(--color-border);">
                                <?php foreach ($rsvpTimeline as $day):
                                    $cumulative += (int)$day['count'];
                                    $cumPct = $totalGuests > 0 ? ($cumulative / $totalGuests) * 100 : 0;
                                ?>
                                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-width: 50px; height: 100%;">
                                    <span style="font-size: 0.7rem; font-weight: bold; margin-bottom: 2px;"><?php echo $cumulative; ?></span>
                                    <div style="width: 80%; background: var(--color-gold); border-radius: 4px 4px 0 0; height: <?php echo max($cumPct, 2); ?>%;" title="<?php echo htmlspecialchars($day['rsvp_date']) . ': ' . $cumulative . ' total (' . round($cumPct, 1) . '%)'; ?>"></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <?php foreach ($rsvpTimeline as $day):
                                    $dateObj2 = new DateTime($day['rsvp_date']);
                                    $formatted2 = $dateObj2->format('n/j');
                                ?>
                                <div style="flex: 1; min-width: 50px; text-align: center;">
                                    <span style="font-size: 0.7rem; display: inline-block; margin-top: 4px; writing-mode: vertical-lr; transform: rotate(180deg); max-height: 60px;" title="<?php echo htmlspecialchars($day['rsvp_date']); ?>"><?php echo $formatted2; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('timeline-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('timeline-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View RSVP Timeline' : 'Hide RSVP Timeline';
                });
                </script>

                <!-- Guests Table -->
                <span id="guests-table" class="guest-count-label">Showing <?php echo count($guests); ?> guest<?php echo count($guests) !== 1 ? 's' : ''; ?></span>
                <div id="bulk-action-bar" class="bulk-action-bar">
                    <span class="bulk-selected-count"><span id="bulk-count">0</span> selected</span>
                    <button type="button" data-action="mark_rehearsal">Mark Rehearsal</button>
                    <button type="button" data-action="unmark_rehearsal">Unmark Rehearsal</button>
                    <button type="button" data-action="mark_child">Mark Child</button>
                    <button type="button" data-action="unmark_child">Unmark Child</button>
                </div>
                <?php
                function getSortUrl($field, $currentSort, $currentOrder) {
                    $params = $_GET;
                    if ($currentSort === $field) {
                        $params['order'] = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
                    } else {
                        $params['sort'] = $field;
                        $params['order'] = 'ASC';
                    }
                    return '/admin-guests?' . http_build_query($params) . '#guests-table';
                }
                function getSortIndicator($field, $currentSort, $currentOrder) {
                    if ($currentSort !== $field) return '';
                    return $currentOrder === 'ASC' ? ' <span class="sort-indicator">▲</span>' : ' <span class="sort-indicator">▼</span>';
                }
                ?>
                <div class="guests-table-container">
                    <table class="guests-table">
                        <thead>
                            <tr>
                                <th class="bulk-check-col"><input type="checkbox" id="bulk-select-all" title="Select all"></th>
                                <th><a href="<?php echo getSortUrl('first_name', $sort, $order); ?>">First Name<?php echo getSortIndicator('first_name', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('last_name', $sort, $order); ?>">Last Name<?php echo getSortIndicator('last_name', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('mailing_group', $sort, $order); ?>">Group #<?php echo getSortIndicator('mailing_group', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('group_name', $sort, $order); ?>">Group Name<?php echo getSortIndicator('group_name', $sort, $order); ?></a></th>
                                <th>Address</th>
                                <th><a href="<?php echo getSortUrl('has_plus_one', $sort, $order); ?>">+1<?php echo getSortIndicator('has_plus_one', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('rehearsal_invited', $sort, $order); ?>">Rehearsal<?php echo getSortIndicator('rehearsal_invited', $sort, $order); ?></a></th>
                                <th>Child</th>
                                <th>Age</th>
                                <th><a href="<?php echo getSortUrl('attending', $sort, $order); ?>">RSVP Status<?php echo getSortIndicator('attending', $sort, $order); ?></a></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $lastGroup = -999;
                            foreach ($guests as $guest):
                                $isPlusOne = !empty($guest['is_plus_one']);
                                $isGroupStart = !$isPlusOne && ($guest['mailing_group'] !== null && $guest['mailing_group'] != $lastGroup);
                                if (!$isPlusOne) $lastGroup = $guest['mailing_group'];
                            ?>
                                <tr class="<?php echo $isGroupStart ? 'group-start' : ''; ?><?php echo $isPlusOne ? ' plus-one-row' : ''; ?>" data-group-name="<?php echo htmlspecialchars($guest['group_name']); ?>">
                                    <td class="bulk-check-col"><?php if (!$isPlusOne): ?><input type="checkbox" class="bulk-guest-check" value="<?php echo $guest['id']; ?>"><?php endif; ?></td>
                                    <td><?php echo htmlspecialchars($guest['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($guest['last_name']); ?></td>
                                    <td><?php echo $guest['mailing_group'] !== null ? htmlspecialchars($guest['mailing_group']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($guest['group_name']); ?></td>
                                    <td><?php
                                        $addrParts = array_filter([
                                            $guest['address_1'] ?? '',
                                            $guest['address_2'] ?? '',
                                        ]);
                                        $cityState = array_filter([
                                            $guest['city'] ?? '',
                                            $guest['state'] ?? '',
                                        ]);
                                        $addrLine1 = implode(', ', $addrParts);
                                        $addrLine2 = implode(', ', $cityState);
                                        if ($guest['zip'] ?? '') $addrLine2 .= ' ' . $guest['zip'];
                                        if ($guest['country'] ?? '') $addrLine2 .= ', ' . $guest['country'];
                                        $addrLine2 = trim($addrLine2, ', ');
                                        $fullAddr = implode('<br>', array_filter([$addrLine1, $addrLine2]));
                                        echo $fullAddr ?: '—';
                                    ?></td>
                                    <td><?php echo $guest['has_plus_one'] ? '✓' : ''; ?></td>
                                    <td><?php echo !empty($guest['rehearsal_invited']) ? '✓' : ''; ?></td>
                                        <td><?php
                                        if (!empty($guest['is_infant'])) echo 'Infant';
                                        elseif (!empty($guest['is_child'])) echo '✓';
                                    ?></td>
                                    <td><?php echo (isset($guest['age']) && $guest['age'] !== null && $guest['age'] !== '') ? (int)$guest['age'] : '—'; ?></td>
                                    <td>
                                        <?php if ($guest['attending'] === 'yes'): ?>
                                            <span class="rsvp-badge rsvp-attending">Attending</span>
                                        <?php elseif ($guest['attending'] === 'no'): ?>
                                            <span class="rsvp-badge rsvp-declined">Declined</span>
                                        <?php else: ?>
                                            <span class="rsvp-badge rsvp-pending">Pending</span>
                                        <?php endif; ?>
                                        <?php if (!empty($guest['notes'])): ?><span title="<?php echo htmlspecialchars($guest['notes']); ?>" style="cursor:help; margin-left:0.3rem;">📝</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$isPlusOne): ?>
                                        <div class="action-links">
                                            <a href="/admin-guests?rsvp=<?php echo $guest['id']; ?>" class="rsvp-link">RSVP</a>
                                            <a href="/admin-guests?edit=<?php echo $guest['id']; ?>" class="edit-link">Edit</a>
                                            <a href="/admin-guests?delete=<?php echo $guest['id']; ?>" class="delete-link"
                                               onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($guest['first_name'] . ' ' . $guest['last_name'])); ?>?');">Delete</a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($guests)): ?>
                                <tr><td colspan="12" style="text-align:center; padding:2rem; color:var(--color-text-secondary);">No guests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
<script>
(function() {
    var searchInput = document.getElementById('guest-search-input');
    if (!searchInput) return;
    var table = document.querySelector('.guests-table');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var countLabel = document.querySelector('.guest-count-label');

    searchInput.addEventListener('input', function() {
        var query = searchInput.value.toLowerCase().trim();
        var visible = 0;
        rows.forEach(function(row) {
            if (row.querySelector('td[colspan]')) {
                // "No guests found" row — hide during filtering
                row.style.display = query ? 'none' : '';
                return;
            }
            var cells = row.querySelectorAll('td');
            var firstName = cells[1] ? cells[1].textContent.toLowerCase() : '';
            var lastName = cells[2] ? cells[2].textContent.toLowerCase() : '';
            var match = !query || firstName.indexOf(query) !== -1 || lastName.indexOf(query) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (countLabel) {
            countLabel.textContent = 'Showing ' + visible + ' guest' + (visible !== 1 ? 's' : '');
        }
    });
})();

// Chart bar click to filter table by group
(function() {
    var bars = document.querySelectorAll('.chart-bar-col');
    if (!bars.length) return;
    var table = document.querySelector('.guests-table');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var countLabel = document.querySelector('.guest-count-label');
    var activeGroup = null;

    bars.forEach(function(bar) {
        bar.addEventListener('click', function() {
            var groupName = this.dataset.groupName;
            if (activeGroup === groupName) {
                // Deselect — show all
                activeGroup = null;
                bars.forEach(function(b) { b.style.opacity = ''; });
                rows.forEach(function(row) { row.style.display = ''; });
            } else {
                activeGroup = groupName;
                bars.forEach(function(b) {
                    b.style.opacity = b.dataset.groupName === groupName ? '1' : '0.3';
                });
                var visible = 0;
                rows.forEach(function(row) {
                    if (row.querySelector('td[colspan]')) { row.style.display = 'none'; return; }
                    var match = row.dataset.groupName === groupName;
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
                if (countLabel) {
                    countLabel.textContent = 'Showing ' + visible + ' guest' + (visible !== 1 ? 's' : '') + ' in "' + groupName + '"';
                }
                document.getElementById('guests-table').scrollIntoView({ behavior: 'smooth' });
                return;
            }
            // Reset count label
            var visible = 0;
            rows.forEach(function(row) {
                if (!row.querySelector('td[colspan]')) visible++;
            });
            if (countLabel) {
                countLabel.textContent = 'Showing ' + visible + ' guest' + (visible !== 1 ? 's' : '');
            }
        });
    });
})();

// Add guest to group
(function() {
    var btn = document.getElementById('add-to-group-btn');
    var container = document.getElementById('extra-guests-container');
    var submitBtn = document.getElementById('add-guest-submit');
    if (!btn || !container) return;
    var count = 0;

    function updateSubmitText() {
        if (!submitBtn) return;
        submitBtn.textContent = container.children.length > 0 ? 'Add Guests' : 'Add Guest';
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        count++;
        var row = document.createElement('div');
        row.className = 'form-row';
        row.style.alignItems = 'flex-end';
        var idx = count - 1;
        row.innerHTML =
            '<div class="form-group required">' +
                '<label for="extra_first_name_' + count + '">First Name</label>' +
                '<input type="text" id="extra_first_name_' + count + '" name="extra_first_names[' + idx + ']" required>' +
            '</div>' +
            '<div class="form-group">' +
                '<label for="extra_last_name_' + count + '">Last Name</label>' +
                '<input type="text" id="extra_last_name_' + count + '" name="extra_last_names[' + idx + ']">' +
            '</div>' +
            '<div class="form-group" style="display:flex; align-items:center; gap:0.5rem; padding-top:1.8rem;">' +
                '<input type="hidden" name="extra_is_child[' + idx + ']" value="0">' +
                '<input type="checkbox" id="extra_is_child_' + count + '" name="extra_is_child[' + idx + ']" value="1" style="width:auto; margin:0;">' +
                '<label for="extra_is_child_' + count + '" style="margin:0; cursor:pointer;">Child</label>' +
            '</div>' +
            '<div class="form-group" style="display:flex; align-items:center; gap:0.5rem; padding-top:1.8rem;">' +
                '<input type="hidden" name="extra_is_infant[' + idx + ']" value="0">' +
                '<input type="checkbox" id="extra_is_infant_' + count + '" name="extra_is_infant[' + idx + ']" value="1" style="width:auto; margin:0;">' +
                '<label for="extra_is_infant_' + count + '" style="margin:0; cursor:pointer;">Infant</label>' +
            '</div>' +
            '<div class="form-group" style="min-width:80px; max-width:90px;">' +
                '<label for="extra_age_' + count + '">Age</label>' +
                '<input type="number" id="extra_age_' + count + '" name="extra_age[' + idx + ']" min="0" max="120" step="1" inputmode="numeric">' +
            '</div>' +
            '<div class="form-group" style="padding-bottom:0.25rem;">' +
                '<button type="button" class="btn-secondary remove-extra-guest" style="color:#8b0000;">Remove</button>' +
            '</div>';
        container.appendChild(row);
        updateSubmitText();
        row.querySelector('.remove-extra-guest').addEventListener('click', function() {
            container.removeChild(row);
            updateSubmitText();
        });
    });
})();

// Bulk operations
(function() {
    var selectAll = document.getElementById('bulk-select-all');
    var bar = document.getElementById('bulk-action-bar');
    var countSpan = document.getElementById('bulk-count');
    if (!selectAll || !bar) return;

    function getCheckboxes() {
        return document.querySelectorAll('.bulk-guest-check');
    }

    function getChecked() {
        return document.querySelectorAll('.bulk-guest-check:checked');
    }

    function updateBar() {
        var checked = getChecked();
        var count = checked.length;
        countSpan.textContent = count;
        if (count > 0) {
            bar.classList.add('visible');
        } else {
            bar.classList.remove('visible');
        }
        // Update select-all state
        var all = getCheckboxes();
        var visibleBoxes = [];
        all.forEach(function(cb) {
            if (cb.closest('tr').style.display !== 'none') visibleBoxes.push(cb);
        });
        var visibleChecked = visibleBoxes.filter(function(cb) { return cb.checked; });
        selectAll.checked = visibleBoxes.length > 0 && visibleChecked.length === visibleBoxes.length;
        selectAll.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visibleBoxes.length;
    }

    selectAll.addEventListener('change', function() {
        var checked = this.checked;
        getCheckboxes().forEach(function(cb) {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = checked;
            }
        });
        updateBar();
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bulk-guest-check')) {
            updateBar();
        }
    });

    bar.addEventListener('click', function(e) {
        var btn = e.target.closest('button[data-action]');
        if (!btn) return;
        var action = btn.dataset.action;
        var checked = getChecked();
        if (checked.length === 0) return;

        var ids = [];
        checked.forEach(function(cb) { ids.push(cb.value); });

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin-guests';
        form.style.display = 'none';

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'bulk_action';
        actionInput.value = action;
        form.appendChild(actionInput);

        var idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.name = 'guest_ids';
        idsInput.value = ids.join(',');
        form.appendChild(idsInput);

        document.body.appendChild(form);
        form.submit();
    });
})();
</script>
</body>
</html>
