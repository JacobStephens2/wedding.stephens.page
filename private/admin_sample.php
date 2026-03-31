<?php

function isAdminSampleMode(): bool
{
    return isset($_GET['sample']) && $_GET['sample'] === '1';
}

function adminUrl(string $path, array $params = []): string
{
    if (isAdminSampleMode()) {
        $params['sample'] = '1';
    }

    $query = http_build_query($params);
    return $query !== '' ? $path . '?' . $query : $path;
}

function renderAdminSampleModeAssets(): void
{
    if (!isAdminSampleMode()) {
        return;
    }
    ?>
    <style>
        .sample-mode-banner {
            max-width: 1200px;
            margin: 1rem auto 0;
            padding: 1rem 1.25rem;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(46, 80, 22, 0.12), rgba(173, 124, 98, 0.18));
            border: 1px solid rgba(46, 80, 22, 0.18);
            color: var(--color-dark);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }
        .sample-mode-banner strong {
            color: var(--color-green);
        }
        .sample-mode-banner p {
            margin: 0.35rem 0 0;
            font-family: 'Crimson Text', serif;
            text-transform: none;
        }
        .sample-mode-banner a {
            color: var(--color-green);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function shouldDecorate(pathname) {
                return pathname === '/check-rsvps' || pathname.indexOf('/admin') === 0;
            }

            document.querySelectorAll('a[href]').forEach(function (link) {
                var href = link.getAttribute('href');
                if (!href || href.charAt(0) === '#') return;
                if (link.hasAttribute('data-sample-ignore')) return;

                try {
                    var url = new URL(href, window.location.origin);
                    if (!shouldDecorate(url.pathname)) return;
                    url.searchParams.set('sample', '1');
                    link.setAttribute('href', url.pathname + url.search + url.hash);
                } catch (err) {
                }
            });

            document.querySelectorAll('form[action]').forEach(function (form) {
                var action = form.getAttribute('action');
                if (!action) return;
                if (form.hasAttribute('data-sample-ignore')) return;

                try {
                    var url = new URL(action, window.location.origin);
                    if (!shouldDecorate(url.pathname)) return;
                    url.searchParams.set('sample', '1');
                    form.setAttribute('action', url.pathname + url.search + url.hash);
                } catch (err) {
                }

                var method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method === 'GET' && !form.querySelector('input[name="sample"]')) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'sample';
                    input.value = '1';
                    form.appendChild(input);
                }

                if (method === 'POST') {
                    form.addEventListener('submit', function (event) {
                        event.preventDefault();
                        window.alert('Sample mode is read-only. This form is shown for portfolio preview only.');
                    });
                }
            });
        });
    </script>
    <?php
}

function renderAdminSampleBanner(string $label = 'Sample Admin Preview'): void
{
    if (!isAdminSampleMode()) {
        return;
    }
    ?>
    <div class="sample-mode-banner">
        <strong><?php echo htmlspecialchars($label); ?></strong>
        <p>Everything on this screen is dummy data for portfolio viewing. Forms and data-changing actions are disabled. <a href="/admin" data-sample-ignore="true">Exit sample mode</a>.</p>
    </div>
    <?php
}

function getSampleGuestRecords(): array
{
    return [
        [
            'id' => 101,
            'first_name' => 'Olivia',
            'last_name' => 'Bennett',
            'group_name' => 'Bennett Family',
            'mailing_group' => 210,
            'email' => 'olivia.bennett@example.test',
            'phone' => '(555) 310-4401',
            'attending' => 'yes',
            'ceremony_attending' => 'yes',
            'reception_attending' => 'yes',
            'dietary' => 'Vegetarian',
            'song_request' => 'Signed, Sealed, Delivered',
            'message' => 'Happy to celebrate with you both.',
            'notes' => 'Sample household with plus-one enabled.',
            'rsvp_submitted_at' => '2026-01-12 10:15:00',
            'rehearsal_invited' => 1,
            'has_plus_one' => 1,
            'plus_one_name' => 'Daniel Bennett',
            'plus_one_attending' => 'yes',
            'plus_one_ceremony_attending' => 'yes',
            'plus_one_reception_attending' => 'yes',
            'plus_one_dietary' => 'No shellfish',
            'plus_one_rehearsal_invited' => 1,
            'is_child' => 0,
            'is_infant' => 0,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '14 Garden Terrace',
            'address_2' => 'Apt 3B',
            'city' => 'Nashville',
            'state' => 'TN',
            'zip' => '37203',
            'country' => 'USA',
        ],
        [
            'id' => 102,
            'first_name' => 'Marcus',
            'last_name' => 'Reed',
            'group_name' => 'Reed Household',
            'mailing_group' => 211,
            'email' => 'marcus.reed@example.test',
            'phone' => '(555) 310-4402',
            'attending' => 'yes',
            'ceremony_attending' => 'yes',
            'reception_attending' => 'no',
            'dietary' => '',
            'song_request' => 'Ain’t No Mountain High Enough',
            'message' => 'Ceremony only for us this weekend.',
            'notes' => 'Sample split RSVP for ceremony only.',
            'rsvp_submitted_at' => '2026-01-15 14:40:00',
            'rehearsal_invited' => 0,
            'has_plus_one' => 0,
            'plus_one_name' => '',
            'plus_one_attending' => null,
            'plus_one_ceremony_attending' => null,
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_rehearsal_invited' => 0,
            'is_child' => 0,
            'is_infant' => 0,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '88 Willow Bend',
            'address_2' => '',
            'city' => 'Memphis',
            'state' => 'TN',
            'zip' => '38103',
            'country' => 'USA',
        ],
        [
            'id' => 103,
            'first_name' => 'Ava',
            'last_name' => 'Nguyen',
            'group_name' => 'Nguyen Family',
            'mailing_group' => 212,
            'email' => 'ava.nguyen@example.test',
            'phone' => '(555) 310-4403',
            'attending' => 'no',
            'ceremony_attending' => 'no',
            'reception_attending' => 'no',
            'dietary' => '',
            'song_request' => '',
            'message' => 'Sending love from afar.',
            'notes' => 'Declined sample RSVP.',
            'rsvp_submitted_at' => '2026-01-18 09:05:00',
            'rehearsal_invited' => 0,
            'has_plus_one' => 0,
            'plus_one_name' => '',
            'plus_one_attending' => null,
            'plus_one_ceremony_attending' => null,
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_rehearsal_invited' => 0,
            'is_child' => 0,
            'is_infant' => 0,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '501 Birch Street',
            'address_2' => '',
            'city' => 'Louisville',
            'state' => 'KY',
            'zip' => '40202',
            'country' => 'USA',
        ],
        [
            'id' => 104,
            'first_name' => 'Noah',
            'last_name' => 'Patel',
            'group_name' => 'Patel Family',
            'mailing_group' => 213,
            'email' => 'noah.patel@example.test',
            'phone' => '(555) 310-4404',
            'attending' => null,
            'ceremony_attending' => null,
            'reception_attending' => null,
            'dietary' => 'Gluten-free',
            'song_request' => '',
            'message' => '',
            'notes' => 'Pending response sample record.',
            'rsvp_submitted_at' => null,
            'rehearsal_invited' => 1,
            'has_plus_one' => 1,
            'plus_one_name' => 'Maya Patel',
            'plus_one_attending' => null,
            'plus_one_ceremony_attending' => null,
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_rehearsal_invited' => 0,
            'is_child' => 0,
            'is_infant' => 0,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '19 Cedar Point',
            'address_2' => '',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip' => '30309',
            'country' => 'USA',
        ],
        [
            'id' => 105,
            'first_name' => 'Ella',
            'last_name' => 'Morales',
            'group_name' => 'Morales Family',
            'mailing_group' => 214,
            'email' => 'ella.morales@example.test',
            'phone' => '(555) 310-4405',
            'attending' => 'yes',
            'ceremony_attending' => 'yes',
            'reception_attending' => 'yes',
            'dietary' => '',
            'song_request' => 'September',
            'message' => 'Bringing the kids and dancing shoes.',
            'notes' => 'Family sample with child guest.',
            'rsvp_submitted_at' => '2026-01-21 18:30:00',
            'rehearsal_invited' => 0,
            'has_plus_one' => 0,
            'plus_one_name' => '',
            'plus_one_attending' => null,
            'plus_one_ceremony_attending' => null,
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_rehearsal_invited' => 0,
            'is_child' => 1,
            'is_infant' => 0,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '740 River Run',
            'address_2' => '',
            'city' => 'Chattanooga',
            'state' => 'TN',
            'zip' => '37402',
            'country' => 'USA',
        ],
        [
            'id' => 106,
            'first_name' => 'Lucas',
            'last_name' => 'Harper',
            'group_name' => 'Harper Family',
            'mailing_group' => 215,
            'email' => 'lucas.harper@example.test',
            'phone' => '(555) 310-4406',
            'attending' => null,
            'ceremony_attending' => null,
            'reception_attending' => null,
            'dietary' => '',
            'song_request' => '',
            'message' => '',
            'notes' => 'Infant sample invitation.',
            'rsvp_submitted_at' => null,
            'rehearsal_invited' => 0,
            'has_plus_one' => 0,
            'plus_one_name' => '',
            'plus_one_attending' => null,
            'plus_one_ceremony_attending' => null,
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_rehearsal_invited' => 0,
            'is_child' => 0,
            'is_infant' => 1,
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
            'address_1' => '12 Meadow Court',
            'address_2' => '',
            'city' => 'Franklin',
            'state' => 'TN',
            'zip' => '37064',
            'country' => 'USA',
        ],
    ];
}

function getSampleGuestStats(array $records): array
{
    $stats = [
        'total' => 0,
        'attending' => 0,
        'declined' => 0,
        'pending' => 0,
        'ceremony' => 0,
        'reception' => 0,
        'ceremony_declined' => 0,
        'reception_declined' => 0,
        'rehearsal' => 0,
        'reception_children' => 0,
        'pending_children' => 0,
        'reception_infants' => 0,
        'pending_infants' => 0,
    ];
    $householdsSeen = [];
    $householdsAttending = [];

    foreach ($records as $guest) {
        if (!empty($guest['mailing_group'])) {
            $householdsSeen[$guest['mailing_group']] = true;
            if ($guest['attending'] === 'yes') {
                $householdsAttending[$guest['mailing_group']] = true;
            }
        }
        $stats['total']++;
        if ($guest['attending'] === 'yes') {
            $stats['attending']++;
        } elseif ($guest['attending'] === 'no') {
            $stats['declined']++;
        } else {
            $stats['pending']++;
        }

        if ($guest['ceremony_attending'] === 'yes') $stats['ceremony']++;
        if ($guest['reception_attending'] === 'yes') $stats['reception']++;
        if ($guest['ceremony_attending'] === 'no') $stats['ceremony_declined']++;
        if ($guest['reception_attending'] === 'no') $stats['reception_declined']++;
        if (!empty($guest['rehearsal_invited'])) $stats['rehearsal']++;
        if (!empty($guest['is_child']) && $guest['reception_attending'] === 'yes') $stats['reception_children']++;
        if (!empty($guest['is_child']) && $guest['attending'] === null) $stats['pending_children']++;
        if (!empty($guest['is_infant']) && $guest['reception_attending'] === 'yes') $stats['reception_infants']++;
        if (!empty($guest['is_infant']) && $guest['attending'] === null) $stats['pending_infants']++;

        if (!empty($guest['has_plus_one'])) {
            $stats['total']++;
            if ($guest['plus_one_attending'] === 'yes') {
                $stats['attending']++;
            } elseif ($guest['plus_one_attending'] === 'no') {
                $stats['declined']++;
            } else {
                $stats['pending']++;
            }

            if ($guest['plus_one_ceremony_attending'] === 'yes') $stats['ceremony']++;
            if ($guest['plus_one_reception_attending'] === 'yes') $stats['reception']++;
            if ($guest['plus_one_ceremony_attending'] === 'no') $stats['ceremony_declined']++;
            if ($guest['plus_one_reception_attending'] === 'no') $stats['reception_declined']++;
            if (!empty($guest['plus_one_rehearsal_invited'])) $stats['rehearsal']++;
            if (!empty($guest['plus_one_is_child']) && $guest['plus_one_reception_attending'] === 'yes') $stats['reception_children']++;
            if (!empty($guest['plus_one_is_child']) && $guest['plus_one_attending'] === null) $stats['pending_children']++;
            if (!empty($guest['plus_one_is_infant']) && $guest['plus_one_reception_attending'] === 'yes') $stats['reception_infants']++;
            if (!empty($guest['plus_one_is_infant']) && $guest['plus_one_attending'] === null) $stats['pending_infants']++;
        }
    }

    $stats['total_households'] = count($householdsSeen);
    $stats['households_attending'] = count($householdsAttending);

    return $stats;
}

function sampleGuestMatchesStatus(array $guest, string $statusFilter): bool
{
    switch ($statusFilter) {
        case 'attending':
            return $guest['attending'] === 'yes';
        case 'declined':
            return $guest['attending'] === 'no';
        case 'pending':
            return $guest['attending'] === null;
        case 'not_declined':
            return $guest['attending'] !== 'no';
        case 'ceremony_yes':
            return $guest['ceremony_attending'] === 'yes';
        case 'ceremony_no':
            return $guest['ceremony_attending'] === 'no';
        case 'reception_yes':
            return $guest['reception_attending'] === 'yes';
        case 'reception_no':
            return $guest['reception_attending'] === 'no';
        case 'rehearsal':
            return !empty($guest['rehearsal_invited']);
        case 'adults':
            return empty($guest['is_child']) && empty($guest['is_infant']) && $guest['reception_attending'] === 'yes';
        case 'children':
            return !empty($guest['is_child']) && $guest['reception_attending'] === 'yes';
        case 'infants':
            return !empty($guest['is_infant']) && $guest['reception_attending'] === 'yes';
        default:
            return true;
    }
}

function getSampleGuestsPageData(string $search = '', string $groupFilter = '', string $statusFilter = '', string $sort = 'mailing_group', string $order = 'ASC'): array
{
    $records = getSampleGuestRecords();
    $filtered = array_values(array_filter($records, function (array $guest) use ($search, $groupFilter, $statusFilter) {
        if ($search !== '') {
            $haystack = strtolower($guest['first_name'] . ' ' . $guest['last_name']);
            if (strpos($haystack, strtolower($search)) === false) {
                return false;
            }
        }

        if ($groupFilter !== '' && (int) $groupFilter !== (int) $guest['mailing_group']) {
            return false;
        }

        if ($statusFilter !== '' && !sampleGuestMatchesStatus($guest, $statusFilter)) {
            return false;
        }

        return true;
    }));

    $allowedSorts = ['first_name', 'last_name', 'mailing_group', 'group_name', 'has_plus_one', 'rehearsal_invited', 'attending'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'mailing_group';
    }
    $direction = strtoupper($order) === 'DESC' ? -1 : 1;

    usort($filtered, function (array $a, array $b) use ($sort, $direction) {
        $av = $a[$sort] ?? '';
        $bv = $b[$sort] ?? '';
        if ($av === $bv) {
            return ($a['id'] <=> $b['id']) * $direction;
        }
        return ($av <=> $bv) * $direction;
    });

    $rows = [];
    foreach ($filtered as $guest) {
        $rows[] = $guest;
        if (!empty($guest['has_plus_one'])) {
            $plusOneRow = [
                'id' => $guest['id'],
                'first_name' => $guest['plus_one_name'] !== '' ? explode(' ', $guest['plus_one_name'], 2)[0] : '(Plus One)',
                'last_name' => $guest['plus_one_name'] !== '' && strpos($guest['plus_one_name'], ' ') !== false ? explode(' ', $guest['plus_one_name'], 2)[1] : '',
                'group_name' => $guest['group_name'],
                'mailing_group' => $guest['mailing_group'],
                'has_plus_one' => 0,
                'attending' => $guest['plus_one_attending'],
                'ceremony_attending' => $guest['plus_one_ceremony_attending'],
                'reception_attending' => $guest['plus_one_reception_attending'],
                'dietary' => $guest['plus_one_dietary'],
                'song_request' => '',
                'message' => '',
                'notes' => 'Sample plus-one record.',
                'rsvp_submitted_at' => $guest['rsvp_submitted_at'],
                'rehearsal_invited' => $guest['plus_one_rehearsal_invited'],
                'is_child' => $guest['plus_one_is_child'],
                'is_infant' => $guest['plus_one_is_infant'],
                'address_1' => $guest['address_1'],
                'address_2' => $guest['address_2'],
                'city' => $guest['city'],
                'state' => $guest['state'],
                'zip' => $guest['zip'],
                'country' => $guest['country'],
                'is_plus_one' => true,
                'phone' => '',
                'email' => '',
            ];

            if ($statusFilter === '' || sampleGuestMatchesStatus($plusOneRow, $statusFilter)) {
                $rows[] = $plusOneRow;
            }
        }
    }

    $groups = [];
    foreach ($records as $guest) {
        $groups[$guest['mailing_group']] = [
            'mailing_group' => $guest['mailing_group'],
            'group_name' => $guest['group_name'],
        ];
    }
    ksort($groups);

    $timelineMap = [];
    foreach ($records as $guest) {
        if (empty($guest['rsvp_submitted_at'])) {
            continue;
        }
        $date = substr($guest['rsvp_submitted_at'], 0, 10);
        $timelineMap[$date] = ($timelineMap[$date] ?? 0) + 1;
    }
    ksort($timelineMap);
    $timeline = [];
    foreach ($timelineMap as $date => $count) {
        $timeline[] = ['rsvp_date' => $date, 'count' => $count];
    }

    return [
        'guests' => $rows,
        'stats' => getSampleGuestStats($records),
        'next_group_number' => max(array_column($records, 'mailing_group')) + 1,
        'existing_groups' => array_values($groups),
        'rsvp_timeline' => $timeline,
    ];
}

function getSampleRsvpData(string $sort = 'rsvp_submitted_at', string $order = 'DESC'): array
{
    $records = array_values(array_filter(getSampleGuestRecords(), function (array $guest) {
        return !empty($guest['rsvp_submitted_at']);
    }));

    $allowedSorts = ['first_name', 'last_name', 'group_name', 'ceremony_attending', 'reception_attending', 'dietary', 'song_request', 'email', 'rsvp_submitted_at'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'rsvp_submitted_at';
    }
    $direction = strtoupper($order) === 'ASC' ? 1 : -1;

    usort($records, function (array $a, array $b) use ($sort, $direction) {
        $av = $a[$sort] ?? '';
        $bv = $b[$sort] ?? '';
        if ($av === $bv) {
            return ($a['id'] <=> $b['id']) * $direction;
        }
        return ($av <=> $bv) * $direction;
    });

    $stats = getSampleGuestStats(getSampleGuestRecords());

    return [
        'rows' => $records,
        'stats' => [
            'total' => $stats['total'],
            'attending' => $stats['attending'],
            'declined' => $stats['declined'],
            'pending' => $stats['pending'],
            'ceremony' => $stats['ceremony'],
            'reception' => $stats['reception'],
            'reception_children' => $stats['reception_children'],
            'reception_infants' => $stats['reception_infants'],
        ],
    ];
}

function getSampleRegistryItems(): array
{
    return [
        [
            'id' => 301,
            'title' => 'Hand-thrown Serving Bowl',
            'description' => 'Sample registry item showing published inventory with a product image and full description.',
            'url' => 'https://example.test/registry/serving-bowl',
            'image_url' => '/images/house-fund.jpg',
            'price' => '72.00',
            'purchased' => 0,
            'purchased_by' => null,
            'created_at' => '2026-01-10 08:00:00',
            'published' => 1,
            'sort_order' => 1,
            'most_wanted' => 1,
        ],
        [
            'id' => 302,
            'title' => 'Linen Table Runner Set',
            'description' => 'Sample unpublished item so visitors can see the admin-only state.',
            'url' => 'https://example.test/registry/table-runner',
            'image_url' => '/images/honeymoon-fund.jpg',
            'price' => '48.00',
            'purchased' => 0,
            'purchased_by' => null,
            'created_at' => '2026-01-12 08:00:00',
            'published' => 0,
            'sort_order' => 2,
            'most_wanted' => 0,
        ],
        [
            'id' => 303,
            'title' => 'Weekend Cabin Gift Card',
            'description' => 'Sample purchased item to preview fulfillment tracking.',
            'url' => 'https://example.test/registry/cabin-stay',
            'image_url' => '/images/honeymoon-fund.jpg',
            'price' => '150.00',
            'purchased' => 1,
            'purchased_by' => 'A Sample Guest',
            'created_at' => '2026-01-14 08:00:00',
            'published' => 1,
            'sort_order' => 3,
            'most_wanted' => 0,
        ],
    ];
}

function getSampleHouseFundContributions(): array
{
    return [
        ['id' => 401, 'amount' => 125.00, 'contributor_name' => 'Sample Friend', 'created_at' => '2026-02-02 12:00:00'],
        ['id' => 402, 'amount' => 250.00, 'contributor_name' => 'The Demo Family', 'created_at' => '2026-02-06 15:30:00'],
        ['id' => 403, 'amount' => 75.00, 'contributor_name' => 'Anonymous Sample', 'created_at' => '2026-02-12 09:45:00'],
    ];
}

function getSampleHoneymoonFundContributions(): array
{
    return [
        ['id' => 451, 'amount' => 180.00, 'contributor_name' => 'Portfolio Guest', 'created_at' => '2026-02-03 13:15:00'],
        ['id' => 452, 'amount' => 95.00, 'contributor_name' => 'Demo Couple Friends', 'created_at' => '2026-02-09 18:00:00'],
        ['id' => 453, 'amount' => 220.00, 'contributor_name' => 'Vacation Fund Sample', 'created_at' => '2026-02-14 11:20:00'],
    ];
}

function getSampleGalleryPhotos(): array
{
    return [
        [
            'id' => 501,
            'path' => 'dating/2025-03-04_Mardi_Gras.JPG',
            'alt' => 'Sample gallery card showing admin metadata editing.',
            'photo_date' => '2025-03-04',
            'position' => 1,
            'story_section' => 'the_sidewalk',
            'story_position' => 1,
        ],
        [
            'id' => 502,
            'path' => 'dating/2025-03-04_Mardi_Gras.JPG',
            'alt' => 'Second sample photo for ordering controls.',
            'photo_date' => '2025-06-18',
            'position' => 2,
            'story_section' => 'proposal',
            'story_position' => 2,
        ],
        [
            'id' => 503,
            'path' => 'dating/2025-03-04_Mardi_Gras.JPG',
            'alt' => 'Gallery-only sample image with no linked story section.',
            'photo_date' => '2025-08-09',
            'position' => 3,
            'story_section' => null,
            'story_position' => null,
        ],
    ];
}

function getSampleSeatingData(): array
{
    $seatingData = [
        1 => [
            'table_id' => 601,
            'table_name' => 'Family Table',
            'capacity' => 8,
            'notes' => 'Immediate family and older relatives.',
            'pos_x' => 24,
            'pos_y' => 28,
            'guests' => [
                [
                    'table_id' => 601,
                    'table_number' => 1,
                    'table_name' => 'Family Table',
                    'capacity' => 8,
                    'table_notes' => 'Immediate family and older relatives.',
                    'pos_x' => 24,
                    'pos_y' => 28,
                    'guest_id' => 101,
                    'first_name' => 'Olivia',
                    'last_name' => 'Bennett',
                    'group_name' => 'Bennett Family',
                    'seat_number' => 1,
                    'reception_attending' => 'yes',
                    'dietary' => 'Vegetarian',
                    'message' => 'Happy to celebrate with you both.',
                    'is_child' => 0,
                    'is_infant' => 0,
                    'has_plus_one' => 1,
                    'plus_one_name' => 'Daniel Bennett',
                    'plus_one_reception_attending' => 'yes',
                    'plus_one_dietary' => 'No shellfish',
                    'plus_one_is_child' => 0,
                    'plus_one_is_infant' => 0,
                ],
                [
                    'table_id' => 601,
                    'table_number' => 1,
                    'table_name' => 'Family Table',
                    'capacity' => 8,
                    'table_notes' => 'Immediate family and older relatives.',
                    'pos_x' => 24,
                    'pos_y' => 28,
                    'guest_id' => 105,
                    'first_name' => 'Ella',
                    'last_name' => 'Morales',
                    'group_name' => 'Morales Family',
                    'seat_number' => 2,
                    'reception_attending' => 'yes',
                    'dietary' => '',
                    'message' => 'Bringing the kids and dancing shoes.',
                    'is_child' => 1,
                    'is_infant' => 0,
                    'has_plus_one' => 0,
                    'plus_one_name' => '',
                    'plus_one_reception_attending' => null,
                    'plus_one_dietary' => '',
                    'plus_one_is_child' => 0,
                    'plus_one_is_infant' => 0,
                ],
            ],
        ],
        2 => [
            'table_id' => 602,
            'table_name' => 'College Friends',
            'capacity' => 10,
            'notes' => 'Friends from Nashville and Memphis.',
            'pos_x' => 68,
            'pos_y' => 36,
            'guests' => [
                [
                    'table_id' => 602,
                    'table_number' => 2,
                    'table_name' => 'College Friends',
                    'capacity' => 10,
                    'table_notes' => 'Friends from Nashville and Memphis.',
                    'pos_x' => 68,
                    'pos_y' => 36,
                    'guest_id' => 102,
                    'first_name' => 'Marcus',
                    'last_name' => 'Reed',
                    'group_name' => 'Reed Household',
                    'seat_number' => 1,
                    'reception_attending' => 'no',
                    'dietary' => '',
                    'message' => 'Ceremony only for us this weekend.',
                    'is_child' => 0,
                    'is_infant' => 0,
                    'has_plus_one' => 0,
                    'plus_one_name' => '',
                    'plus_one_reception_attending' => null,
                    'plus_one_dietary' => '',
                    'plus_one_is_child' => 0,
                    'plus_one_is_infant' => 0,
                ],
            ],
        ],
    ];

    $unseatedGuests = [
        [
            'id' => 701,
            'first_name' => 'Priya',
            'last_name' => 'Shah',
            'group_name' => 'Late Additions',
            'reception_attending' => 'yes',
            'dietary' => 'Nut-free',
            'message' => 'Needs assignment in preview data.',
            'is_child' => 0,
            'is_infant' => 0,
            'has_plus_one' => 0,
            'plus_one_name' => '',
            'plus_one_reception_attending' => null,
            'plus_one_dietary' => '',
            'plus_one_is_child' => 0,
            'plus_one_is_infant' => 0,
        ],
    ];

    $stats = ['tables' => 2, 'seated' => 4, 'unseated' => 1, 'dietary' => 3];
    $allTablesJson = json_encode([
        ['id' => 601, 'number' => 1, 'name' => 'Family Table', 'capacity' => 8, 'guest_count' => 3, 'pos_x' => 24, 'pos_y' => 28],
        ['id' => 602, 'number' => 2, 'name' => 'College Friends', 'capacity' => 10, 'guest_count' => 1, 'pos_x' => 68, 'pos_y' => 36],
    ]);

    return [
        'seating_data' => $seatingData,
        'unseated_guests' => $unseatedGuests,
        'stats' => $stats,
        'tables_json' => $allTablesJson,
    ];
}
