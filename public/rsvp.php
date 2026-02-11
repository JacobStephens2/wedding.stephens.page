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
        
        <div class="form-group required">
            <label for="rsvp-email">Email Address</label>
            <input type="email" id="rsvp-email" placeholder="your@email.com" required>
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
            <p>Thank you for your RSVP! We've received your response and look forward to celebrating with you.</p>
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
    .group-member-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .group-member-name {
        font-size: 1.15rem;
        color: var(--color-dark);
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
        font-size: 0.85rem;
        transition: all 0.2s;
        color: #666;
    }
    .attending-toggle button:first-child {
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
        border-style: dashed;
        margin-left: 1.5rem;
        margin-top: -0.5rem;
    }
    .plus-one-label {
        font-style: italic;
        color: #666;
    }
    .plus-one-details.hidden {
        display: none;
    }
    .plus-one-details {
        margin-top: 0.5rem;
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
                    const rsvpd = guest.rsvp_submitted_at ? '<span class="search-result-rsvpd">Already RSVPd</span>' : '';
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
            
            // Build group RSVP form
            const selectedName = data.selected_guest.first_name + ' ' + (data.selected_guest.last_name || '');
            if (groupMembers.length > 1) {
                rsvpGroupDesc.textContent = 'Please indicate attendance for each member of your party.';
            } else {
                rsvpGroupDesc.textContent = 'Please indicate whether you will be attending.';
            }
            
            let html = '';
            groupMembers.forEach(function(member) {
                const memberName = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                const currentAttending = member.attending;
                const currentDietary = member.dietary || '';
                
                html += '<div class="group-member-card" data-member-id="' + member.id + '">'
                     + '<div class="group-member-header">'
                     + '<span class="group-member-name">' + escapeHtml(memberName) + '</span>'
                     + '<div class="attending-toggle">'
                     + '<button type="button" class="btn-attending' + (currentAttending === 'yes' ? ' active-yes' : '') + '" data-value="yes">Attending</button>'
                     + '<button type="button" class="btn-attending' + (currentAttending === 'no' ? ' active-no' : '') + '" data-value="no">Not Attending</button>'
                     + '</div>'
                     + '</div>'
                     + '<div class="group-member-dietary">'
                     + '<label for="dietary-' + member.id + '">Dietary restrictions or allergies</label>'
                     + '<input type="text" id="dietary-' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(currentDietary) + '">'
                     + '</div>'
                     + '</div>';
                
                // Plus one card
                if (parseInt(member.has_plus_one)) {
                    const poName = member.plus_one_name || '';
                    const poAttending = member.plus_one_attending;
                    const poDietary = member.plus_one_dietary || '';
                    const bringingChecked = poAttending === 'yes' ? true : false;
                    const notBringingChecked = poAttending === 'no' ? true : false;
                    
                    html += '<div class="group-member-card plus-one-card" data-plus-one-for="' + member.id + '">'
                         + '<div class="group-member-header">'
                         + '<span class="group-member-name plus-one-label">Guest of ' + escapeHtml(member.first_name) + '</span>'
                         + '<div class="attending-toggle">'
                         + '<button type="button" class="btn-plus-one-attending' + (bringingChecked ? ' active-yes' : '') + '" data-value="yes">Bringing</button>'
                         + '<button type="button" class="btn-plus-one-attending' + (notBringingChecked ? ' active-no' : '') + '" data-value="no">Not Bringing</button>'
                         + '</div>'
                         + '</div>'
                         + '<div class="plus-one-details' + (bringingChecked ? '' : ' hidden') + '">'
                         + '<div class="group-member-dietary">'
                         + '<label for="plus-one-name-' + member.id + '">Guest\'s name</label>'
                         + '<input type="text" id="plus-one-name-' + member.id + '" placeholder="Enter your guest\'s name..." value="' + escapeHtml(poName) + '">'
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
            
            // Pre-fill message and song if available
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
            
            // Attach attending toggle handlers
            groupMembersList.querySelectorAll('.btn-attending').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const card = this.closest('.group-member-card');
                    const toggle = this.closest('.attending-toggle');
                    
                    // Clear active states
                    toggle.querySelectorAll('button').forEach(function(b) {
                        b.classList.remove('active-yes', 'active-no');
                    });
                    
                    // Set active
                    const value = this.dataset.value;
                    if (value === 'yes') {
                        this.classList.add('active-yes');
                        card.classList.add('attending');
                        card.classList.remove('declined');
                    } else {
                        this.classList.add('active-no');
                        card.classList.add('declined');
                        card.classList.remove('attending');
                    }
                });
            });
            
            // Attach plus-one toggle handlers
            groupMembersList.querySelectorAll('.btn-plus-one-attending').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const card = this.closest('.plus-one-card');
                    const toggle = this.closest('.attending-toggle');
                    const details = card.querySelector('.plus-one-details');
                    
                    // Clear active states
                    toggle.querySelectorAll('button').forEach(function(b) {
                        b.classList.remove('active-yes', 'active-no');
                    });
                    
                    const value = this.dataset.value;
                    if (value === 'yes') {
                        this.classList.add('active-yes');
                        card.classList.add('attending');
                        card.classList.remove('declined');
                        if (details) details.classList.remove('hidden');
                    } else {
                        this.classList.add('active-no');
                        card.classList.add('declined');
                        card.classList.remove('attending');
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
        
        if (!email) {
            showRsvpError('Please enter your email address.');
            return;
        }
        
        // Collect guest responses
        const guestData = [];
        let hasResponse = false;
        
        groupMembersList.querySelectorAll('.group-member-card:not(.plus-one-card)').forEach(function(card) {
            const memberId = parseInt(card.dataset.memberId);
            const activeBtn = card.querySelector('.btn-attending.active-yes, .btn-attending.active-no');
            const attending = activeBtn ? activeBtn.dataset.value : '';
            const dietary = card.querySelector('input[type="text"]').value.trim();
            
            if (attending) hasResponse = true;
            
            const entry = {
                id: memberId,
                attending: attending,
                dietary: dietary
            };
            
            // Check for plus-one card
            const plusOneCard = groupMembersList.querySelector('.plus-one-card[data-plus-one-for="' + memberId + '"]');
            if (plusOneCard) {
                const poActiveBtn = plusOneCard.querySelector('.btn-plus-one-attending.active-yes, .btn-plus-one-attending.active-no');
                const poAttending = poActiveBtn ? poActiveBtn.dataset.value : '';
                const poName = (plusOneCard.querySelector('input[id^="plus-one-name-"]') || {}).value || '';
                const poDietary = (plusOneCard.querySelector('input[id^="plus-one-dietary-"]') || {}).value || '';
                
                entry.plus_one_attending = poAttending;
                entry.plus_one_name = poName.trim();
                entry.plus_one_dietary = poDietary.trim();
            }
            
            guestData.push(entry);
        });
        
        if (!hasResponse) {
            showRsvpError('Please indicate attendance for at least one guest.');
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
                
                // Build summary
                let summaryHtml = '<h3>Your RSVP Summary</h3>';
                guestData.forEach(function(gd) {
                    const member = groupMembers.find(function(m) { return m.id === gd.id || m.id == gd.id; });
                    if (member && gd.attending) {
                        const name = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                        summaryHtml += '<div class="rsvp-summary-item">'
                            + '<span>' + escapeHtml(name) + '</span>'
                            + '<span>' + (gd.attending === 'yes' ? '✓ Attending' : '✗ Not Attending') + '</span>'
                            + '</div>';
                        
                        // Plus one summary
                        if (gd.plus_one_attending === 'yes' && gd.plus_one_name) {
                            summaryHtml += '<div class="rsvp-summary-item">'
                                + '<span>' + escapeHtml(gd.plus_one_name) + ' (guest)</span>'
                                + '<span>✓ Attending</span>'
                                + '</div>';
                        } else if (gd.plus_one_attending === 'no') {
                            summaryHtml += '<div class="rsvp-summary-item">'
                                + '<span>Guest of ' + escapeHtml(member.first_name) + '</span>'
                                + '<span>✗ Not Bringing</span>'
                                + '</div>';
                        }
                    }
                });
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
        btnSubmit.textContent = 'Submit RSVP';
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
