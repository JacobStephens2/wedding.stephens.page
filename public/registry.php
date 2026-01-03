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
$houseFundTotal = 0;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT id, title, description, url, image_url, price, purchased, purchased_by, created_at
        FROM registry_items
        ORDER BY purchased ASC, created_at DESC
    ");
    $items = $stmt->fetchAll();
    
    // Get total house fund contributions
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM house_fund_contributions
    ");
    $result = $stmt->fetch();
    $houseFundTotal = $result['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error loading registry items: " . $e->getMessage());
}

$page_title = "Registry - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <div class="registry-container">
        <h2>Our Wedding Registry</h2>

        <!-- House Fund Section -->
        <div class="house-fund-section">
            <h3>House Fund</h3>
            <p>We would be honored if you would like to contribute to our house fund as a wedding gift.</p>
            
            <div class="house-fund-payment-methods">
                <div class="payment-method">
                    <strong>Venmo:</strong> @Melissa-Longua
                </div>
                <div class="payment-method">
                    <strong>Check:</strong> Please make checks payable to Melissa Longua. If mailing, send to:<br>
                    <span class="address">3815 Haverford Ave, Unit 1<br>Philadelphia, PA 19104</span>
                </div>
            </div>
            
            <div class="house-fund-total">
                <p class="total-label">Total Contributed:</p>
                <p class="total-amount">$<?php echo number_format($houseFundTotal, 2); ?></p>
            </div>
            
            <div class="house-fund-form-container">
                <p>If you've contributed, please let us know the amount (optional):</p>
                <form id="house-fund-form" class="house-fund-form">
                    <div class="form-group">
                        <label for="contribution-amount">Amount</label>
                        <input type="number" id="contribution-amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label for="contributor-name">Your Name (optional)</label>
                        <input type="text" id="contributor-name" name="contributor_name" placeholder="Enter your name">
                    </div>
                    <button type="submit" class="btn">Submit Contribution</button>
                </form>
                <div id="house-fund-message" class="house-fund-message" style="display: none;"></div>
            </div>
        </div>

        <?php if (empty($items)): ?>
            <p class="registry-fallback">
                <a href="https://www.zola.com/registry/jacobandmelissaapril11" target="_blank" rel="noopener noreferrer">
                    Visit our registry on Zola →
                </a>
            </p>
        <?php else: ?>
            <div class="registry-sort-controls">
                <label for="sort-select">Sort by:</label>
                <select id="sort-select" class="sort-select">
                    <option value="">- Select -</option>
                    <option value="price-low">Price: Low to High</option>
                    <option value="price-high">Price: High to Low</option>
                </select>
            </div>
            <div class="registry-items-grid" id="registry-items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="registry-item-card <?php echo $item['purchased'] ? 'purchased' : ''; ?>" 
                         data-item-id="<?php echo $item['id']; ?>"
                         data-price="<?php echo $item['price'] ?? '0'; ?>"
                         data-purchased="<?php echo $item['purchased'] ? '1' : '0'; ?>">
                        <?php if ($item['image_url']): ?>
                            <div class="registry-item-image">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy" class="registry-item-image-clickable">
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
                            <?php if ($item['price']): ?>
                                <p class="registry-item-price">$<?php echo number_format($item['price'], 2); ?></p>
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
    
    // Sorting functionality
    const sortSelect = document.getElementById('sort-select');
    const itemsGrid = document.getElementById('registry-items-grid');
    
    if (sortSelect && itemsGrid) {
        sortSelect.addEventListener('change', function() {
            const sortValue = this.value;
            
            // If "Sort" option is selected (empty value), don't sort
            if (!sortValue) {
                return;
            }
            
            const items = Array.from(itemsGrid.querySelectorAll('.registry-item-card'));
            
            items.sort((a, b) => {
                const priceA = parseFloat(a.getAttribute('data-price')) || 0;
                const priceB = parseFloat(b.getAttribute('data-price')) || 0;
                const purchasedA = parseInt(a.getAttribute('data-purchased')) || 0;
                const purchasedB = parseInt(b.getAttribute('data-purchased')) || 0;
                
                switch(sortValue) {
                    case 'price-low':
                        // Sort by price low to high, available items first
                        if (purchasedA !== purchasedB) {
                            return purchasedA - purchasedB; // Available (0) before purchased (1)
                        }
                        return priceA - priceB;
                    
                    case 'price-high':
                        // Sort by price high to low, available items first
                        if (purchasedA !== purchasedB) {
                            return purchasedA - purchasedB; // Available (0) before purchased (1)
                        }
                        return priceB - priceA;
                }
            });
            
            // Clear and re-append sorted items
            items.forEach(item => itemsGrid.appendChild(item));
        });
    }
    
    // Lightbox functionality for registry item images
    const registryImages = document.querySelectorAll('.registry-item-image-clickable');
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxClose = document.querySelector('.lightbox-close');
    
    if (registryImages.length > 0 && lightbox && lightboxImage) {
        // Open lightbox when clicking a registry image
        registryImages.forEach(img => {
            img.addEventListener('click', function() {
                lightboxImage.src = this.src;
                lightboxImage.alt = this.alt;
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });
        
        // Close lightbox function
        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Close on X button click
        if (lightboxClose) {
            lightboxClose.addEventListener('click', closeLightbox);
        }
        
        // Close on background click
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                closeLightbox();
            }
        });
    }
    
    // House Fund form submission
    const houseFundForm = document.getElementById('house-fund-form');
    const houseFundMessage = document.getElementById('house-fund-message');
    const totalAmountElement = document.querySelector('.total-amount');
    
    if (houseFundForm) {
        houseFundForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(document.getElementById('contribution-amount').value);
            const contributorName = document.getElementById('contributor-name').value.trim();
            
            if (!amount || amount <= 0) {
                showHouseFundMessage('Please enter a valid amount.', 'error');
                return;
            }
            
            // Disable form during submission
            const submitBtn = houseFundForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            fetch('/api/submit-contribution.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                    contributor_name: contributorName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total amount
                    if (totalAmountElement) {
                        totalAmountElement.textContent = '$' + parseFloat(data.total).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                    // Reset form
                    houseFundForm.reset();
                    showHouseFundMessage(data.message || 'Thank you for your contribution!', 'success');
                } else {
                    showHouseFundMessage(data.error || 'An error occurred. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showHouseFundMessage('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Contribution';
            });
        });
    }
    
    function showHouseFundMessage(message, type) {
        if (!houseFundMessage) return;
        
        houseFundMessage.textContent = message;
        houseFundMessage.className = 'house-fund-message ' + (type === 'success' ? 'success' : 'error');
        houseFundMessage.style.display = 'block';
        
        // Hide message after 5 seconds
        setTimeout(() => {
            houseFundMessage.style.display = 'none';
        }, 5000);
    }
});
</script>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image" src="" alt="">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>





