<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "RSVP - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <h1 class="page-title">RSVP</h1>
    
    <div class="locations-info">
        <h2>Wedding and Reception</h2>
        <div class="location-item">
            <h3>St. Agatha St. James Parish</h3>
            <p>3728 Chestnut St, Philadelphia, PA 19104</p>
        </div>
        <div class="location-item">
            <h3>Bala Golf Club</h3>
            <p>2200 Belmont Ave, Philadelphia, PA 19131</p>
        </div>
    </div>

    <div class="rsvp-deadline" style="text-align:center; margin-bottom:2rem; padding:1rem; background:rgba(127,143,101,0.08); border-radius:8px;">
        <p style="font-family:'Crimson Text',serif; font-size:1.15rem; color:var(--color-dark); margin:0;">
            Please RSVP by <strong style="color:var(--color-green);">March 11, 2026</strong>
        </p>
    </div>

    <!-- Step 1: Name Lookup -->
    <div class="form-container" id="step-lookup">
        <h2 class="rsvp-step-title">Find Your Invitation</h2>
        <p class="rsvp-step-desc">Please enter your first and/or last name to find your invitation.</p>
        <div class="form-group">
            <label for="guest-search">Your Name</label>
            <input type="text" id="guest-search" placeholder="Enter your first or last name..." autocomplete="off">
        </div>
        <button type="button" class="btn" id="btn-search">Find My Invite</button>
        
        <div id="search-results" class="search-results" style="display:none;">
            <!-- Populated by JS -->
        </div>
        
        <div id="search-error" class="rsvp-not-found" style="display:none;">
            <p>Oops! We're having trouble finding your invite. Please try another spelling of your name or contact the couple.</p>
        </div>
    </div>
    
    <!-- Step 2: Group RSVP Form -->
    <div class="form-container" id="step-rsvp" style="display:none;">
        <h2 class="rsvp-step-title">RSVP for Your Party</h2>
        <p class="rsvp-step-desc" id="rsvp-group-desc"></p>
        
        <div id="group-members-list" class="group-members-list">
            <!-- Populated by JS -->
        </div>
        
        <div class="form-group">
            <label for="rsvp-email">Email Address (Optional)</label>
            <input type="email" id="rsvp-email" placeholder="your@email.com">
        </div>
        
        <div class="form-group">
            <label for="rsvp-message">Message (Optional)</label>
            <textarea id="rsvp-message" placeholder="Any message for the couple..."></textarea>
        </div>
        
        <div class="form-group">
            <label for="rsvp-song">Song Request (Optional)</label>
            <textarea id="rsvp-song" placeholder="Is there a song that would get you on the dance floor?"></textarea>
        </div>
        
        <div class="rsvp-form-actions">
            <button type="button" class="btn" id="btn-submit-rsvp">Submit RSVP</button>
            <button type="button" class="btn-back" id="btn-back-search">← Search Again</button>
        </div>
        
        <div id="rsvp-error" class="alert alert-error" style="display:none;">
            <p></p>
        </div>
    </div>
    
    <!-- Step 3: Success -->
    <div class="form-container" id="step-success" style="display:none;">
        <div class="alert alert-success">
            <p id="success-message">Thank you for your RSVP! We've received your response and look forward to celebrating with you.</p>
        </div>
        <div id="rsvp-summary" class="rsvp-summary">
            <!-- Populated by JS -->
        </div>
    </div>
</main>

<style>
    .rsvp-step-title {
        color: var(--color-green);
        margin-bottom: 0.5rem;
    }
    .rsvp-step-desc {
        font-family: 'Crimson Text', serif;
        color: #666;
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
    }
    .search-results {
        margin-top: 1.5rem;
    }
    .search-results h3 {
        font-size: 1rem;
        color: var(--color-green);
        margin-bottom: 0.75rem;
    }
    .search-result-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        font-family: 'Crimson Text', serif;
        font-size: 1.05rem;
    }
    .search-result-item:hover {
        border-color: var(--color-green);
        background-color: rgba(127, 143, 101, 0.05);
    }
    .search-result-name {
        font-weight: bold;
        color: var(--color-dark);
    }
    .search-result-group {
        font-size: 0.9rem;
        color: #888;
    }
    .search-result-rsvpd {
        font-size: 0.85rem;
        color: var(--color-green);
        font-style: italic;
    }
    .rsvp-not-found {
        margin-top: 1.5rem;
        padding: 1.25rem;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 6px;
    }
    .rsvp-not-found p {
        color: #856404;
        font-family: 'Crimson Text', serif;
        margin: 0;
        font-size: 1.05rem;
    }
    
    /* Group members list */
    .group-members-list {
        margin-bottom: 1.5rem;
    }
    .group-member-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        background: white;
        transition: border-color 0.2s;
    }
    .group-member-card.attending {
        border-color: var(--color-green);
        background: rgba(127, 143, 101, 0.03);
    }
    .group-member-card.declined {
        border-color: #dc3545;
        background: rgba(220, 53, 69, 0.02);
    }
    .group-member-name {
        font-size: 1.15rem;
        color: var(--color-dark);
        display: block;
        margin-bottom: 0.75rem;
    }
    .event-rsvp-rows {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        margin-bottom: 0.75rem;
    }
    .event-rsvp-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .event-label {
        font-family: 'Crimson Text', serif;
        font-size: 1rem;
        color: var(--color-dark);
    }
    .event-sublabel {
        font-size: 0.85rem;
        color: #888;
    }
    .attending-toggle {
        display: flex;
        gap: 0;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #ccc;
    }
    .attending-toggle button {
        padding: 0.4rem 1rem;
        border: none;
        background: white;
        cursor: pointer;
        font-family: 'Cinzel', serif;
        font-size: 0.8rem;
        transition: all 0.2s;
        color: #666;
    }
    .attending-toggle button:not(:last-child) {
        border-right: 1px solid #ccc;
    }
    .attending-toggle button.active-yes {
        background: var(--color-green);
        color: white;
    }
    .attending-toggle button.active-no {
        background: #dc3545;
        color: white;
    }
    .plus-one-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .group-member-dietary {
        margin-top: 0.5rem;
    }
    .group-member-dietary label {
        font-family: 'Crimson Text', serif;
        font-size: 0.95rem;
        color: #666;
        display: block;
        margin-bottom: 0.25rem;
    }
    .group-member-dietary input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: 'Crimson Text', serif;
        font-size: 1rem;
    }
    .group-member-dietary input:focus {
        border-color: var(--color-green);
        outline: none;
        box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
    }
    
    .rsvp-form-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    .btn-back {
        background: none;
        border: none;
        color: var(--color-green);
        cursor: pointer;
        font-family: 'Cinzel', serif;
        font-size: 0.95rem;
        padding: 0.5rem;
        transition: color 0.3s;
    }
    .btn-back:hover {
        color: var(--color-gold);
    }
    
    .rsvp-summary {
        margin-top: 1.5rem;
        font-family: 'Crimson Text', serif;
    }
    .rsvp-summary h3 {
        color: var(--color-green);
        margin-bottom: 0.75rem;
    }
    .rsvp-summary-item {
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
    }
    .rsvp-summary-item:last-child {
        border-bottom: none;
    }
    .rsvp-summary-note {
        margin-top: 1.25rem;
        font-size: 0.95rem;
        color: #888;
        font-style: italic;
    }
    
    /* Loading state */
    .btn.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    .btn.loading::after {
        content: '...';
    }
    
    #guest-search {
        font-size: 1.1rem;
    }
    
    /* Plus one styles */
    .plus-one-card {
        border-style: solid;
    }
    .plus-one-label {
        font-style: italic;
        color: var(--color-dark);
        font-size: 0.9rem;
    }
    .plus-one-details.hidden {
        display: none;
    }
    .plus-one-details {
        margin-top: 0.75rem;
    }
    .plus-one-name-group {
        margin-bottom: 0.5rem;
    }
    .plus-one-name-group label {
        font-family: 'Cinzel', serif;
        font-size: 0.95rem;
        color: var(--color-dark);
        display: block;
        margin-bottom: 0.35rem;
        font-weight: 600;
    }
    .plus-one-name-group input {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 2px solid var(--color-green);
        border-radius: 6px;
        font-family: 'Crimson Text', serif;
        font-size: 1.15rem;
        color: var(--color-dark);
        background: rgba(127, 143, 101, 0.04);
    }
    .plus-one-name-group input:focus {
        border-color: var(--color-green);
        outline: none;
        box-shadow: 0 0 0 3px rgba(127, 143, 101, 0.25);
    }
    .plus-one-name-group input::placeholder {
        color: #aaa;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('guest-search');
    const btnSearch = document.getElementById('btn-search');
    const searchResults = document.getElementById('search-results');
    const searchError = document.getElementById('search-error');
    const stepLookup = document.getElementById('step-lookup');
    const stepRsvp = document.getElementById('step-rsvp');
    const stepSuccess = document.getElementById('step-success');
    const groupMembersList = document.getElementById('group-members-list');
    const rsvpGroupDesc = document.getElementById('rsvp-group-desc');
    const btnSubmit = document.getElementById('btn-submit-rsvp');
    const btnBack = document.getElementById('btn-back-search');
    const rsvpError = document.getElementById('rsvp-error');
    
    let selectedGuestId = null;
    let groupMembers = [];
    let isUpdate = false;
    
    // Search for guests
    async function searchGuests() {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            searchResults.style.display = 'none';
            searchError.style.display = 'none';
            return;
        }
        
        btnSearch.classList.add('loading');
        btnSearch.textContent = 'Searching';
        
        try {
            const resp = await fetch('/api/guest-search?q=' + encodeURIComponent(query));
            const data = await resp.json();
            
            if (data.guests && data.guests.length > 0) {
                searchError.style.display = 'none';
                searchResults.style.display = 'block';
                
                let html = '<h3>Select your name:</h3>';
                data.guests.forEach(function(guest) {
                    const fullName = guest.first_name + (guest.last_name ? ' ' + guest.last_name : '');
                    const rsvpd = guest.rsvp_submitted_at ? '<span class="search-result-rsvpd">RSVPd — click to update</span>' : '';
                    html += '<div class="search-result-item" data-guest-id="' + guest.id + '">'
                         + '<div>'
                         + '<span class="search-result-name">' + escapeHtml(fullName) + '</span>'
                         + '</div>'
                         + rsvpd
                         + '</div>';
                });
                searchResults.innerHTML = html;
                
                // Attach click handlers
                searchResults.querySelectorAll('.search-result-item').forEach(function(item) {
                    item.addEventListener('click', function() {
                        selectGuest(parseInt(this.dataset.guestId));
                    });
                });
            } else {
                searchResults.style.display = 'none';
                searchError.style.display = 'block';
            }
        } catch (err) {
            console.error('Search error:', err);
            searchResults.style.display = 'none';
            searchError.style.display = 'block';
        }
        
        btnSearch.classList.remove('loading');
        btnSearch.textContent = 'Find My Invite';
    }
    
    // Select a guest and load their group
    async function selectGuest(guestId) {
        selectedGuestId = guestId;
        
        try {
            const resp = await fetch('/api/guest-group?guest_id=' + guestId);
            const data = await resp.json();
            
            if (data.error) {
                alert(data.error);
                return;
            }
            
            groupMembers = data.group_members;

            // Detect if this is an update (any member has already submitted)
            isUpdate = groupMembers.some(function(m) { return !!m.rsvp_submitted_at; });

            // Update heading and submit button text based on update vs first-time
            document.querySelector('#step-rsvp .rsvp-step-title').textContent = isUpdate ? 'Update Your RSVP' : 'RSVP for Your Party';
            btnSubmit.textContent = isUpdate ? 'Update RSVP' : 'Submit RSVP';

            // Build group RSVP form
            const selectedName = data.selected_guest.first_name + ' ' + (data.selected_guest.last_name || '');
            if (groupMembers.length > 1) {
                rsvpGroupDesc.textContent = 'Please indicate attendance for each member of your party.';
            } else {
                rsvpGroupDesc.textContent = 'Please indicate whether you will be attending.';
            }
            
            function eventToggleHtml(btnClass, currentVal) {
                return '<div class="attending-toggle">'
                     + '<button type="button" class="' + btnClass + (currentVal === 'yes' ? ' active-yes' : '') + '" data-value="yes">Attending</button>'
                     + '<button type="button" class="' + btnClass + (currentVal === 'no' ? ' active-no' : '') + '" data-value="no">Not Attending</button>'
                     + '</div>';
            }
            
            let html = '';
            groupMembers.forEach(function(member) {
                const memberName = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                const currentDietary = member.dietary || '';
                const curCeremony = member.ceremony_attending;
                const curReception = member.reception_attending;
                
                html += '<div class="group-member-card" data-member-id="' + member.id + '">'
                     + '<span class="group-member-name">' + escapeHtml(memberName) + '</span>'
                     + '<div class="event-rsvp-rows">'
                     + '<div class="event-rsvp-row">'
                     + '<span class="event-label">Ceremony <span class="event-sublabel">(St. Agatha St. James)</span></span>'
                     + eventToggleHtml('btn-ceremony', curCeremony)
                     + '</div>'
                     + '<div class="event-rsvp-row">'
                     + '<span class="event-label">Reception <span class="event-sublabel">(Bala Golf Club)</span></span>'
                     + eventToggleHtml('btn-reception', curReception)
                     + '</div>'
                     + '</div>'
                     + '<div class="group-member-dietary">'
                     + '<label for="dietary-' + member.id + '">Dietary restrictions or allergies</label>'
                     + '<input type="text" id="dietary-' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(currentDietary) + '">'
                     + '</div>'
                     + '</div>';
                
                if (parseInt(member.has_plus_one)) {
                    const poName = member.plus_one_name || '';
                    const poCeremony = member.plus_one_ceremony_attending;
                    const poReception = member.plus_one_reception_attending;
                    const poDietary = member.plus_one_dietary || '';
                    const hasAnyResponse = poCeremony !== null || poReception !== null;
                    const notBringing = poCeremony === 'no' && poReception === 'no';
                    const showDetails = !hasAnyResponse || !notBringing;
                    
                    html += '<div class="group-member-card plus-one-card" data-plus-one-for="' + member.id + '">'
                         + '<div class="plus-one-header">'
                         + '<span class="group-member-name plus-one-label">Guest of ' + escapeHtml(member.first_name) + '</span>'
                         + '<div class="attending-toggle">'
                         + '<button type="button" class="btn-po-toggle' + (!notBringing ? ' active-yes' : '') + '" data-value="bringing">Bringing</button>'
                         + '<button type="button" class="btn-po-toggle' + (notBringing ? ' active-no' : '') + '" data-value="not-bringing">Not Bringing</button>'
                         + '</div>'
                         + '</div>'
                         + '<div class="plus-one-details' + (notBringing ? ' hidden' : '') + '">'
                         + '<div class="plus-one-name-group">'
                         + '<label for="plus-one-name-' + member.id + '">Guest\'s Full Name</label>'
                         + '<input type="text" id="plus-one-name-' + member.id + '" placeholder="Enter your guest\'s full name..." value="' + escapeHtml(poName) + '">'
                         + '</div>'
                         + '<div class="event-rsvp-rows">'
                         + '<div class="event-rsvp-row">'
                         + '<span class="event-label">Ceremony <span class="event-sublabel">(St. Agatha St. James)</span></span>'
                         + eventToggleHtml('btn-po-ceremony', poCeremony)
                         + '</div>'
                         + '<div class="event-rsvp-row">'
                         + '<span class="event-label">Reception <span class="event-sublabel">(Bala Golf Club)</span></span>'
                         + eventToggleHtml('btn-po-reception', poReception)
                         + '</div>'
                         + '</div>'
                         + '<div class="group-member-dietary">'
                         + '<label for="plus-one-dietary-' + member.id + '">Dietary restrictions or allergies</label>'
                         + '<input type="text" id="plus-one-dietary-' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(poDietary) + '">'
                         + '</div>'
                         + '</div>'
                         + '</div>';
                }
            });
            groupMembersList.innerHTML = html;
            
            // Pre-fill email if any member has one
            const emailInput = document.getElementById('rsvp-email');
            for (let m of groupMembers) {
                if (m.email) {
                    emailInput.value = m.email;
                    break;
                }
            }
            
            for (let m of groupMembers) {
                if (m.message) {
                    document.getElementById('rsvp-message').value = m.message;
                    break;
                }
            }
            for (let m of groupMembers) {
                if (m.song_request) {
                    document.getElementById('rsvp-song').value = m.song_request;
                    break;
                }
            }
            
            // Attach event toggle handlers (ceremony/reception for main guests)
            groupMembersList.querySelectorAll('.btn-ceremony, .btn-reception, .btn-po-ceremony, .btn-po-reception').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const toggle = this.closest('.attending-toggle');
                    toggle.querySelectorAll('button').forEach(function(b) {
                        b.classList.remove('active-yes', 'active-no');
                    });
                    this.classList.add(this.dataset.value === 'yes' ? 'active-yes' : 'active-no');
                    
                    // Update card border state based on any attendance
                    const card = this.closest('.group-member-card');
                    const anyYes = card.querySelectorAll('.active-yes').length > 0;
                    const allNo = card.querySelectorAll('.active-no').length > 0 && card.querySelectorAll('.active-yes').length === 0;
                    card.classList.toggle('attending', anyYes);
                    card.classList.toggle('declined', allNo && !anyYes);
                });
            });
            
            // Attach plus-one bringing/not-bringing toggle
            groupMembersList.querySelectorAll('.btn-po-toggle').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const card = this.closest('.plus-one-card');
                    const toggle = this.closest('.attending-toggle');
                    const details = card.querySelector('.plus-one-details');
                    toggle.querySelectorAll('button').forEach(function(b) {
                        b.classList.remove('active-yes', 'active-no');
                    });
                    if (this.dataset.value === 'bringing') {
                        this.classList.add('active-yes');
                        if (details) details.classList.remove('hidden');
                        // Clear any forced 'no' on event toggles when switching back to bringing
                        card.querySelectorAll('.btn-po-ceremony.active-no, .btn-po-reception.active-no').forEach(function(b) {
                            b.classList.remove('active-no');
                        });
                    } else {
                        this.classList.add('active-no');
                        if (details) details.classList.add('hidden');
                    }
                });
            });
            
            // Show RSVP step
            stepLookup.style.display = 'none';
            stepRsvp.style.display = 'block';
            stepRsvp.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
        } catch (err) {
            console.error('Error loading group:', err);
            alert('An error occurred. Please try again.');
        }
    }
    
    // Submit RSVP
    async function submitRsvp() {
        rsvpError.style.display = 'none';
        
        const email = document.getElementById('rsvp-email').value.trim();
        const message = document.getElementById('rsvp-message').value.trim();
        const songRequest = document.getElementById('rsvp-song').value.trim();
        
        // Collect guest responses
        const guestData = [];
        let hasResponse = false;
        
        groupMembersList.querySelectorAll('.group-member-card:not(.plus-one-card)').forEach(function(card) {
            const memberId = parseInt(card.dataset.memberId);
            const ceremonyBtn = card.querySelector('.btn-ceremony.active-yes, .btn-ceremony.active-no');
            const receptionBtn = card.querySelector('.btn-reception.active-yes, .btn-reception.active-no');
            const ceremonyAttending = ceremonyBtn ? ceremonyBtn.dataset.value : '';
            const receptionAttending = receptionBtn ? receptionBtn.dataset.value : '';
            const dietary = card.querySelector('input[type="text"]').value.trim();
            
            if (ceremonyAttending || receptionAttending) hasResponse = true;
            
            const entry = {
                id: memberId,
                ceremony_attending: ceremonyAttending,
                reception_attending: receptionAttending,
                dietary: dietary
            };
            
            // Check for plus-one card
            const plusOneCard = groupMembersList.querySelector('.plus-one-card[data-plus-one-for="' + memberId + '"]');
            if (plusOneCard) {
                const poToggleBtn = plusOneCard.querySelector('.btn-po-toggle.active-no');
                const notBringing = !!poToggleBtn;
                const poCeremonyBtn = plusOneCard.querySelector('.btn-po-ceremony.active-yes, .btn-po-ceremony.active-no');
                const poReceptionBtn = plusOneCard.querySelector('.btn-po-reception.active-yes, .btn-po-reception.active-no');
                const poName = (plusOneCard.querySelector('input[id^="plus-one-name-"]') || {}).value || '';
                const poDietary = (plusOneCard.querySelector('input[id^="plus-one-dietary-"]') || {}).value || '';
                
                entry.plus_one_ceremony_attending = notBringing ? 'no' : (poCeremonyBtn ? poCeremonyBtn.dataset.value : '');
                entry.plus_one_reception_attending = notBringing ? 'no' : (poReceptionBtn ? poReceptionBtn.dataset.value : '');
                entry.plus_one_name = poName.trim();
                entry.plus_one_dietary = poDietary.trim();
            }
            
            guestData.push(entry);
        });
        
        if (!hasResponse) {
            showRsvpError('Please indicate attendance for at least one guest for the ceremony or reception.');
            return;
        }
        
        btnSubmit.classList.add('loading');
        btnSubmit.textContent = 'Submitting';
        
        try {
            const resp = await fetch('/api/submit-group-rsvp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    guests: guestData,
                    email: email,
                    message: message,
                    song_request: songRequest
                })
            });
            
            const data = await resp.json();
            
            if (data.success) {
                // Show success
                stepRsvp.style.display = 'none';
                stepSuccess.style.display = 'block';

                // Set success message based on update vs first-time
                document.getElementById('success-message').textContent = isUpdate
                    ? "Your RSVP has been updated! We've received your changes."
                    : "Thank you for your RSVP! We've received your response and look forward to celebrating with you.";

                // Build summary
                function eventSummaryLabel(ceremony, reception) {
                    var events = [];
                    if (ceremony === 'yes') events.push('Ceremony');
                    if (reception === 'yes') events.push('Reception');
                    return events.length > 0 ? ('✓ ' + events.join(' & ')) : '✗ Not Attending';
                }
                
                let summaryHtml = '<h3>Your RSVP Summary</h3>';
                guestData.forEach(function(gd) {
                    const member = groupMembers.find(function(m) { return m.id === gd.id || m.id == gd.id; });
                    if (member && (gd.ceremony_attending || gd.reception_attending)) {
                        const name = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                        summaryHtml += '<div class="rsvp-summary-item">'
                            + '<span>' + escapeHtml(name) + '</span>'
                            + '<span>' + eventSummaryLabel(gd.ceremony_attending, gd.reception_attending) + '</span>'
                            + '</div>';
                        if (gd.dietary) {
                            summaryHtml += '<div class="rsvp-summary-item">'
                                + '<span>&nbsp;&nbsp;Dietary: ' + escapeHtml(gd.dietary) + '</span>'
                                + '</div>';
                        }

                        if (gd.plus_one_ceremony_attending || gd.plus_one_reception_attending) {
                            const poLabel = gd.plus_one_name || ('Guest of ' + member.first_name);
                            const poCeremony = gd.plus_one_ceremony_attending;
                            const poReception = gd.plus_one_reception_attending;
                            summaryHtml += '<div class="rsvp-summary-item">'
                                + '<span>' + escapeHtml(poLabel) + ' (guest)</span>'
                                + '<span>' + eventSummaryLabel(poCeremony, poReception) + '</span>'
                                + '</div>';
                            if (gd.plus_one_dietary) {
                                summaryHtml += '<div class="rsvp-summary-item">'
                                    + '<span>&nbsp;&nbsp;Dietary: ' + escapeHtml(gd.plus_one_dietary) + '</span>'
                                    + '</div>';
                            }
                        }
                    }
                });
                if (songRequest) {
                    summaryHtml += '<div class="rsvp-summary-item" style="margin-top: 0.75rem;">'
                        + '<span>Song Request: ' + escapeHtml(songRequest) + '</span>'
                        + '</div>';
                }
                summaryHtml += '<p class="rsvp-summary-note">Need to make changes? Just search for your name again to update your RSVP.</p>';
                document.getElementById('rsvp-summary').innerHTML = summaryHtml;
                
                stepSuccess.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                showRsvpError(data.error || 'An error occurred. Please try again.');
            }
        } catch (err) {
            console.error('Submit error:', err);
            showRsvpError('An error occurred. Please try again.');
        }
        
        btnSubmit.classList.remove('loading');
        btnSubmit.textContent = isUpdate ? 'Update RSVP' : 'Submit RSVP';
    }
    
    function showRsvpError(msg) {
        rsvpError.querySelector('p').textContent = msg;
        rsvpError.style.display = 'block';
        rsvpError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Event listeners
    btnSearch.addEventListener('click', searchGuests);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchGuests();
        }
    });
    
    btnSubmit.addEventListener('click', submitRsvp);
    
    btnBack.addEventListener('click', function() {
        stepRsvp.style.display = 'none';
        stepLookup.style.display = 'block';
        stepLookup.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
