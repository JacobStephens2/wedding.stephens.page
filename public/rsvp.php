<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "RSVP - Jacob & Melissa";
include __DIR__ . '/includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $attending = $_POST['attending'] ?? '';
    $guests = intval($_POST['guests'] ?? 1);
    $dietary = trim($_POST['dietary'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $songRequest = trim($_POST['song_request'] ?? '');
    
    // Collect guest names
    $guestNames = [];
    if ($guests > 1) {
        for ($i = 1; $i < $guests; $i++) {
            $guestName = trim($_POST['guest_name_' . $i] ?? '');
            if (!empty($guestName)) {
                $guestNames[] = $guestName;
            }
        }
    }
    $guestNamesJson = !empty($guestNames) ? json_encode($guestNames) : null;
    
    // Validation
    if (empty($name) || empty($email) || empty($attending)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Store in database
        require_once __DIR__ . '/../private/db.php';
        $dbSaved = false;
        
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                INSERT INTO rsvps (name, email, attending, guests, guest_names, dietary, message, song_request)
                VALUES (:name, :email, :attending, :guests, :guest_names, :dietary, :message, :song_request)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':attending' => $attending,
                ':guests' => $guests,
                ':guest_names' => $guestNamesJson,
                ':dietary' => !empty($dietary) ? $dietary : null,
                ':message' => !empty($message) ? $message : null,
                ':song_request' => !empty($songRequest) ? $songRequest : null,
            ]);
            
            $dbSaved = true;
        } catch (Exception $e) {
            error_log("Database save failed: " . $e->getMessage());
            // Continue to try email even if database fails
        }
        
        // Send email notification to both recipients
        require_once __DIR__ . '/../private/email_handler.php';
        
        $emailBody = "New RSVP Submission\n\n";
        $emailBody .= "Name: $name\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Attending: $attending\n";
        $emailBody .= "Number of Guests: $guests\n";
        if (!empty($guestNames)) {
            $emailBody .= "Guest Names: " . implode(', ', $guestNames) . "\n";
        }
        if (!empty($dietary)) {
            $emailBody .= "Dietary Restrictions: $dietary\n";
        }
        if (!empty($message)) {
            $emailBody .= "Message: $message\n";
        }
        if (!empty($songRequest)) {
            $emailBody .= "Song Request: $songRequest\n";
        }
        $emailBody .= "Check RSVPs at https://wedding.stephens.page/check-rsvps with password 'song'";
        
        $subject = 'New RSVP - ' . $name;
        
        // Send to both email addresses
        $rsvpEmails = [
            'melissa.longua@gmail.com',
            'jacob@stephens.page'
        ];
        
        $emailSent = false;
        foreach ($rsvpEmails as $emailAddress) {
            if (sendEmail($emailAddress, $subject, $emailBody)) {
                $emailSent = true;
            }
        }
        
        // Success if either database or email succeeded (prefer database)
        if ($dbSaved || $emailSent) {
            $success = true;
        } else {
            $error = 'There was an error submitting your RSVP. Please try again later.';
        }
    }
}
?>

<main class="page-container">
    <h1 class="page-title">RSVP</h1>
    
    <div class="locations-info">
        <h2>Wedding Locations</h2>
        <div class="location-item">
            <h3>St. Agatha St. James Parish</h3>
            <p>3728 Chestnut St, Philadelphia, PA 19104</p>
        </div>
        <div class="location-item">
            <h3>Bala Golf Club</h3>
            <p>2200 Belmont Ave, Philadelphia, PA 19131</p>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="form-container">
            <div class="alert alert-success">
                <p>Thank you for your RSVP! We've received your response and look forward to celebrating with you.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="form-container">
                <div class="alert alert-error">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="/rsvp">
                <div class="form-group required">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                
                <div class="form-group required">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group required">
                    <label for="attending">Will you be attending?</label>
                    <select id="attending" name="attending" required>
                        <option value="">Please select...</option>
                        <option value="Yes" <?php echo (($_POST['attending'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes, I'll be there!</option>
                        <option value="No" <?php echo (($_POST['attending'] ?? '') === 'No') ? 'selected' : ''; ?>>Sorry, I can't make it</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="guests">Number of Guests</label>
                    <input type="number" id="guests" name="guests" min="1" value="<?php echo htmlspecialchars($_POST['guests'] ?? '1'); ?>">
                </div>
                
                <div id="guest-names-container" class="form-group" style="display: none;">
                    <label>Additional Guest Names</label>
                    <div id="guest-names-fields"></div>
                </div>
                
                <div class="form-group">
                    <label for="dietary">Dietary Restrictions or Allergies</label>
                    <textarea id="dietary" name="dietary" placeholder="Please let us know about any dietary restrictions or allergies..."><?php echo htmlspecialchars($_POST['dietary'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="message">Message (Optional)</label>
                    <textarea id="message" name="message" placeholder="Any additional message for the couple..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="song_request">Song Request (Optional)</label>
                    <textarea id="song_request" name="song_request" placeholder="Is there a song that would get you on the dance floor?"><?php echo htmlspecialchars($_POST['song_request'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">Submit RSVP</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const guestsInput = document.getElementById('guests');
    const guestNamesContainer = document.getElementById('guest-names-container');
    const guestNamesFields = document.getElementById('guest-names-fields');
    
    // Store existing guest name values from form submission
    const existingGuestNames = {};
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guests'])) {
        $guests = intval($_POST['guests'] ?? 1);
        for ($i = 1; $i < $guests; $i++) {
            $guestName = trim($_POST['guest_name_' . $i] ?? '');
            if (!empty($guestName)) {
                echo "existingGuestNames[" . $i . "] = " . json_encode($guestName) . ";\n";
            }
        }
    }
    ?>
    
    function updateGuestNameFields() {
        const guests = parseInt(guestsInput.value) || 1;
        
        if (guests > 1) {
            guestNamesContainer.style.display = 'block';
            guestNamesFields.innerHTML = '';
            
            // Create input fields for additional guests (guests - 1, since the first guest is the RSVP submitter)
            for (let i = 1; i < guests; i++) {
                const fieldGroup = document.createElement('div');
                fieldGroup.style.marginBottom = '0.75rem';
                
                const label = document.createElement('label');
                label.setAttribute('for', 'guest_name_' + i);
                label.textContent = 'Guest ' + i + ' Name';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.id = 'guest_name_' + i;
                input.name = 'guest_name_' + i;
                input.placeholder = 'Enter guest name';
                input.style.width = '100%';
                input.className = 'form-control';
                if (existingGuestNames[i]) {
                    input.value = existingGuestNames[i];
                }
                
                fieldGroup.appendChild(label);
                fieldGroup.appendChild(input);
                guestNamesFields.appendChild(fieldGroup);
            }
        } else {
            guestNamesContainer.style.display = 'none';
            guestNamesFields.innerHTML = '';
        }
    }
    
    // Initialize on page load
    updateGuestNameFields();
    
    // Update when guests number changes
    guestsInput.addEventListener('change', updateGuestNameFields);
    guestsInput.addEventListener('input', updateGuestNameFields);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

