<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "Contact - Jacob & Melissa";
include __DIR__ . '/includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Send email
        require_once __DIR__ . '/../private/email_handler.php';
        
        $emailBody = "Contact Form Submission\n\n";
        $emailBody .= "From: $name <$email>\n";
        $emailBody .= "Subject: $subject\n\n";
        $emailBody .= "Message:\n$message\n";
        
        $emailSent = sendEmail(
            $_ENV['CONTACT_EMAIL'],
            'Contact Form: ' . $subject,
            $emailBody,
            $email
        );
        
        if ($emailSent) {
            $success = true;
        } else {
            $error = 'There was an error sending your message. Please try again later.';
        }
    }
}
?>

<main class="page-container">
    <h1 class="page-title">Contact Us</h1>

    <div class="form-container mailing-address-section">
        <h3>Mailing Address</h3>
        <p class="mailing-address">
            Jacob Stephens<br>
            3815 Haverford Ave, Unit 1<br>
            Philadelphia, PA 19104
        </p>
    </div>
    
    <?php if ($success): ?>
        <div class="form-container">
            <div class="alert alert-success">
                <p>Thank you for your message! We'll get back to you soon.</p>
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
            <form method="POST" action="/contact">
                <div class="form-group required">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                
                <div class="form-group required">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group required">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                </div>
                
                <div class="form-group required">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required placeholder="Your message..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

