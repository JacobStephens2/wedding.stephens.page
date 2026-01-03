<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';

/**
 * Convert text to sentence case (first letter capitalized, rest lowercase)
 */
function toSentenceCase($text) {
    if (empty($text)) {
        return $text;
    }
    // Convert to lowercase first, then capitalize first letter
    return ucfirst(mb_strtolower(trim($text), 'UTF-8'));
}

$items = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT id, title, description, url, image_url, purchased, purchased_by, created_at
        FROM registry_items
        ORDER BY purchased ASC, created_at DESC
    ");
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading registry items: " . $e->getMessage());
}

$page_title = "Registry - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <div class="registry-container">
        <h2>Our Wedding Registry</h2>

        <?php if (empty($items)): ?>
            <p class="registry-fallback">
                <a href="https://www.zola.com/registry/jacobandmelissaapril11" target="_blank" rel="noopener noreferrer">
                    Visit our registry on Zola →
                </a>
            </p>
        <?php else: ?>
            <div class="registry-items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="registry-item-card <?php echo $item['purchased'] ? 'purchased' : ''; ?>" data-item-id="<?php echo $item['id']; ?>">
                        <?php if ($item['image_url']): ?>
                            <div class="registry-item-image">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="registry-item-content">
                            <h3 class="registry-item-title">
                                <?php echo htmlspecialchars($item['title']); ?>
                                <?php if ($item['purchased']): ?>
                                    <span class="purchased-badge">Purchased</span>
                                <?php endif; ?>
                            </h3>
                            <?php if ($item['description']): ?>
                                <p class="registry-item-description"><?php echo htmlspecialchars(toSentenceCase($item['description'])); ?></p>
                            <?php endif; ?>
                            <div class="registry-item-actions">
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-registry-link">
                                    View Item →
                                </a>
                                <?php if (!$item['purchased']): ?>
                                    <button class="btn btn-mark-purchased" data-item-id="<?php echo $item['id']; ?>">
                                        Mark as Purchased
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-mark-available" data-item-id="<?php echo $item['id']; ?>">
                                        Mark as Available
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['purchased'] && $item['purchased_by']): ?>
                                <p class="registry-item-purchased-by">Purchased by: <?php echo htmlspecialchars($item['purchased_by']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal for marking item as purchased -->
<div id="purchase-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3>Mark Item as Purchased</h3>
        <p>Would you like to let others know you've purchased this item?</p>
        <form id="purchase-form">
            <div class="form-group">
                <label for="purchaser-name">Your Name (optional)</label>
                <input type="text" id="purchaser-name" name="purchaser_name" placeholder="Enter your name">
            </div>
            <input type="hidden" id="purchase-item-id" name="item_id">
            <div class="modal-actions">
                <button type="submit" class="btn">Mark as Purchased</button>
                <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const markPurchasedButtons = document.querySelectorAll('.btn-mark-purchased, .btn-mark-available');
    const modal = document.getElementById('purchase-modal');
    const modalClose = document.querySelector('.modal-close');
    const modalCancel = document.querySelector('.modal-cancel');
    const purchaseForm = document.getElementById('purchase-form');
    const purchaserNameInput = document.getElementById('purchaser-name');
    const purchaseItemIdInput = document.getElementById('purchase-item-id');
    
    function openModal(itemId) {
        purchaseItemIdInput.value = itemId;
        purchaserNameInput.value = '';
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Handle mark as purchased buttons
    markPurchasedButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const isPurchased = this.classList.contains('btn-mark-available');
            
            if (isPurchased) {
                // If already purchased, just toggle without modal
                markItemPurchased(itemId, '', true);
            } else {
                // If not purchased, show modal
                openModal(itemId);
            }
        });
    });
    
    // Close modal handlers
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    if (modalCancel) {
        modalCancel.addEventListener('click', closeModal);
    }
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Handle form submission
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const itemId = purchaseItemIdInput.value;
            const purchaserName = purchaserNameInput.value.trim();
            markItemPurchased(itemId, purchaserName, false);
            closeModal();
        });
    }
    
    function markItemPurchased(itemId, purchaserName, isToggle) {
        fetch('/api/mark-purchased.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                purchaser_name: purchaserName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show updated status
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to update item'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>





