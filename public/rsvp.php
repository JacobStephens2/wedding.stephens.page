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
                INSERT INTO rsvps (name, email, attending, guests, dietary, message)
                VALUES (:name, :email, :attending, :guests, :dietary, :message)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':attending' => $attending,
                ':guests' => $guests,
                ':dietary' => !empty($dietary) ? $dietary : null,
                ':message' => !empty($message) ? $message : null,
            ]);
            
            $dbSaved = true;
        } catch (Exception $e) {
            error_log("Database save failed: " . $e->getMessage());
            // Continue to try email even if database fails
        }
        
        // Send email notification
        require_once __DIR__ . '/../private/email_handler.php';
        
        $emailBody = "New RSVP Submission\n\n";
        $emailBody .= "Name: $name\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Attending: $attending\n";
        $emailBody .= "Number of Guests: $guests\n";
        if (!empty($dietary)) {
            $emailBody .= "Dietary Restrictions: $dietary\n";
        }
        if (!empty($message)) {
            $emailBody .= "Message: $message\n";
        }
        $emailBody .= "Check RSVPs at https://wedding.stephens.page/check-rsvps with password 'song'";
        
        $emailSent = sendEmail(
            $_ENV['RSVP_EMAIL'],
            'New RSVP - ' . $name,
            $emailBody
        );
        
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
                
                <div class="form-group">
                    <label for="dietary">Dietary Restrictions or Allergies</label>
                    <textarea id="dietary" name="dietary" placeholder="Please let us know about any dietary restrictions or allergies..."><?php echo htmlspecialchars($_POST['dietary'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="message">Message (Optional)</label>
                    <textarea id="message" name="message" placeholder="Any additional message for the couple..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">Submit RSVP</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

