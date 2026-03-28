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
$honeymoonFundTotal = 0;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT id, title, description, url, image_url, price, purchased, purchased_by, created_at, most_wanted
        FROM registry_items
        WHERE published = TRUE
        ORDER BY purchased ASC, most_wanted DESC, sort_order ASC, id ASC
    ");
    $items = $stmt->fetchAll();
    
    // Get total house fund contributions
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM house_fund_contributions
    ");
    $result = $stmt->fetch();
    $houseFundTotal = $result['total'] ?? 0;

    // Get total honeymoon fund contributions
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM honeymoon_fund_contributions
        ");
        $result = $stmt->fetch();
        $honeymoonFundTotal = $result['total'] ?? 0;
    } catch (Exception $e) {
        // Allow registry page to load even if the honeymoon table isn't set up yet.
        $honeymoonFundTotal = 0;
        error_log("Error loading honeymoon fund total: " . $e->getMessage());
    }
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
        <div class="house-fund-section collapsed" id="house-fund-section">
            <button type="button" class="house-fund-header" id="house-fund-toggle" aria-expanded="false" aria-controls="house-fund-body">
                <h3>House Fund</h3>
                <span class="house-fund-toggle-icon" aria-hidden="true"></span>
            </button>
            <div class="house-fund-body" id="house-fund-body">
                <div class="house-fund-image">
                    <img src="/images/house-fund.jpg" alt="House Fund">
                </div>
                <p>We would be honored if you would like to contribute to our house fund as a wedding gift.</p>
                
                <div class="house-fund-info-container">
                    <div class="house-fund-payment-methods">
                        <div class="payment-method">
                            <strong>Venmo:</strong> @Melissa-Longua
                        </div>
                        <div class="payment-method">
                            <strong>Check:</strong> Please make checks payable to Jacob Stephens. If mailing, send to:<br>
                            <span class="address">3815 Haverford Ave, Unit 1<br>Philadelphia, PA 19104</span>
                        </div>
                    </div>
                    
                    <div class="house-fund-total">
                        <p class="total-label">Total Contributed:</p>
                        <p class="total-amount" id="house-fund-total-amount">$<?php echo number_format($houseFundTotal, 2); ?></p>
                    </div>
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
        </div>

        <!-- Honeymoon Fund Section -->
        <div class="honeymoon-fund-section collapsed" id="honeymoon-fund-section">
            <button type="button" class="honeymoon-fund-header" id="honeymoon-fund-toggle" aria-expanded="false" aria-controls="honeymoon-fund-body">
                <h3>Honeymoon Fund</h3>
                <span class="honeymoon-fund-toggle-icon" aria-hidden="true"></span>
            </button>
            <div class="honeymoon-fund-body" id="honeymoon-fund-body">
                <div class="honeymoon-fund-image">
                    <img src="/images/honeymoon-fund.jpg" alt="Honeymoon Fund">
                </div>
                <p>We're honeymooning in Puerto Rico! We would be honored if you would like to contribute to our honeymoon fund as a wedding gift.</p>
                
                <div class="honeymoon-fund-info-container">
                    <div class="honeymoon-fund-payment-methods">
                        <div class="payment-method">
                            <strong>Venmo:</strong> @Melissa-Longua
                        </div>
                        <div class="payment-method">
                            <strong>Check:</strong> Please make checks payable to Jacob Stephens. If mailing, send to:<br>
                            <span class="address">3815 Haverford Ave, Unit 1<br>Philadelphia, PA 19104</span>
                        </div>
                    </div>
                    
                    <div class="honeymoon-fund-total">
                        <p class="total-label">Total Contributed:</p>
                        <p class="total-amount" id="honeymoon-fund-total-amount">$<?php echo number_format($honeymoonFundTotal, 2); ?></p>
                    </div>
                </div>
                
                <div class="honeymoon-fund-form-container">
                    <p>If you've contributed, please let us know the amount (optional):</p>
                    <form id="honeymoon-fund-form" class="honeymoon-fund-form">
                        <div class="form-group">
                            <label for="honeymoon-contribution-amount">Amount</label>
                            <input type="number" id="honeymoon-contribution-amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="honeymoon-contributor-name">Your Name (optional)</label>
                            <input type="text" id="honeymoon-contributor-name" name="contributor_name" placeholder="Enter your name">
                        </div>
                        <button type="submit" class="btn">Submit Contribution</button>
                    </form>
                    <div id="honeymoon-fund-message" class="honeymoon-fund-message" style="display: none;"></div>
                </div>
            </div>
        </div>

        <div class="registry-prompt" id="registry-prompt">
            <p><strong>Please remember:</strong> If you've purchased an item, please click "Mark as Purchased" so others know it's been taken.</p>
        </div>
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
                <div class="registry-item-card <?php echo $item['purchased'] ? 'purchased' : ''; ?> <?php echo !empty($item['most_wanted']) ? 'most-wanted' : ''; ?>"
                        data-item-id="<?php echo $item['id']; ?>"
                        data-price="<?php echo $item['price'] ?? '0'; ?>"
                        data-purchased="<?php echo $item['purchased'] ? '1' : '0'; ?>"
                        data-most-wanted="<?php echo !empty($item['most_wanted']) ? '1' : '0'; ?>">
                    <?php if ($item['image_url']): ?>
                        <div class="registry-item-image">
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener noreferrer" class="registry-item-image-link" data-item-id="<?php echo $item['id']; ?>" data-item-title="<?php echo htmlspecialchars($item['title']); ?>">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="registry-item-content">
                        <h3 class="registry-item-title">
                            <?php echo htmlspecialchars($item['title']); ?>
                            <?php if (!empty($item['most_wanted']) && !$item['purchased']): ?>
                                <span class="most-wanted-badge">Most Wanted</span>
                            <?php endif; ?>
                            <?php if ($item['purchased']): ?>
                                <span class="purchased-badge">Purchased</span>
                            <?php endif; ?>
                        </h3>
                        <?php if ($item['description']): ?>
                            <p class="registry-item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($item['price']): ?>
                            <p class="registry-item-price">$<?php echo number_format($item['price'], 2); ?></p>
                        <?php endif; ?>
                        <div class="registry-item-actions">
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-registry-link" data-item-id="<?php echo $item['id']; ?>" data-item-title="<?php echo htmlspecialchars($item['title']); ?>">
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
        
    </div>
</main>

<!-- Modal for marking item as purchased -->
<div id="purchase-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3>Mark Item as Purchased</h3>
        <p>If you've purchased this item, please mark it here so other guests know it's been taken.</p>
        <form id="purchase-form">
            <div class="form-group">
                <label for="purchaser-name">Your Name (only visible to Melissa and Jacob)</label>
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

<!-- Modal for return prompt after viewing item -->
<div id="return-prompt-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close return-prompt-close">&times;</span>
        <h3>Did you purchase this item?</h3>
        <p id="return-prompt-item-title"></p>
        <p>If you purchased this item, please mark it as purchased so others know it's been taken.</p>
        <div class="modal-actions">
            <button type="button" class="btn" id="return-prompt-yes">Yes, I purchased it</button>
            <button type="button" class="btn btn-secondary" id="return-prompt-no">No, I didn't purchase it</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // House fund section fold/unfold
    const HOUSE_FUND_COLLAPSED_KEY = 'registry-house-fund-collapsed';
    const houseFundSection = document.getElementById('house-fund-section');
    const houseFundToggle = document.getElementById('house-fund-toggle');
    const houseFundBody = document.getElementById('house-fund-body');
    if (houseFundSection && houseFundToggle && houseFundBody) {
        const setCollapsed = function(collapsed) {
            houseFundSection.classList.toggle('collapsed', collapsed);
            houseFundToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            try {
                localStorage.setItem(HOUSE_FUND_COLLAPSED_KEY, collapsed ? '1' : '0');
            } catch (e) {}
        };
        const isCollapsed = function() {
            try {
                const stored = localStorage.getItem(HOUSE_FUND_COLLAPSED_KEY);
                // Default to collapsed if not yet set.
                if (stored === null) return true;
                return stored === '1';
            } catch (e) {
                return true;
            }
        };
        setCollapsed(isCollapsed());
        houseFundToggle.addEventListener('click', function() {
            setCollapsed(!houseFundSection.classList.contains('collapsed'));
        });
    }

    // Honeymoon fund section fold/unfold
    const HONEYMOON_FUND_COLLAPSED_KEY = 'registry-honeymoon-fund-collapsed';
    const honeymoonFundSection = document.getElementById('honeymoon-fund-section');
    const honeymoonFundToggle = document.getElementById('honeymoon-fund-toggle');
    const honeymoonFundBody = document.getElementById('honeymoon-fund-body');
    if (honeymoonFundSection && honeymoonFundToggle && honeymoonFundBody) {
        const setCollapsed = function(collapsed) {
            honeymoonFundSection.classList.toggle('collapsed', collapsed);
            honeymoonFundToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            try {
                localStorage.setItem(HONEYMOON_FUND_COLLAPSED_KEY, collapsed ? '1' : '0');
            } catch (e) {}
        };
        const isCollapsed = function() {
            try {
                const stored = localStorage.getItem(HONEYMOON_FUND_COLLAPSED_KEY);
                // Default to collapsed if not yet set.
                if (stored === null) return true;
                return stored === '1';
            } catch (e) {
                return true;
            }
        };
        setCollapsed(isCollapsed());
        honeymoonFundToggle.addEventListener('click', function() {
            setCollapsed(!honeymoonFundSection.classList.contains('collapsed'));
        });
    }

    // Sticky prompt functionality
    const registryPrompt = document.getElementById('registry-prompt');
    if (registryPrompt) {
        const header = document.querySelector('header');
        const headerHeight = header ? header.offsetHeight : 0;
        const promptOffset = registryPrompt.offsetTop;
        const promptHeight = registryPrompt.offsetHeight;
        
        function handleScroll() {
            if (window.scrollY > promptOffset) {
                registryPrompt.classList.add('sticky');
                registryPrompt.style.top = headerHeight + 'px';
                // Add padding to body to prevent content jump
                if (!document.body.style.paddingTop) {
                    document.body.style.paddingTop = promptHeight + 'px';
                }
            } else {
                registryPrompt.classList.remove('sticky');
                registryPrompt.style.top = '';
                document.body.style.paddingTop = '';
            }
        }
        
        window.addEventListener('scroll', handleScroll);
        // Check on load in case page is already scrolled
        handleScroll();
    }
    
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
    
    // Return prompt modal elements
    const returnPromptModal = document.getElementById('return-prompt-modal');
    const returnPromptClose = document.querySelector('.return-prompt-close');
    const returnPromptYes = document.getElementById('return-prompt-yes');
    const returnPromptNo = document.getElementById('return-prompt-no');
    const returnPromptItemTitle = document.getElementById('return-prompt-item-title');
    let pendingItemId = null;
    
    function showReturnPrompt(itemId, itemTitle) {
        // Check if item is still available (not purchased)
        const itemCard = document.querySelector(`[data-item-id="${itemId}"]`);
        if (itemCard) {
            const isPurchased = itemCard.getAttribute('data-purchased') === '1';
            if (!isPurchased) {
                // Show the return prompt modal immediately
                pendingItemId = itemId;
                returnPromptItemTitle.textContent = itemTitle;
                returnPromptModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
    }
    
    function closeReturnPromptModal() {
        returnPromptModal.style.display = 'none';
        document.body.style.overflow = '';
        // Clear the stored item data
        localStorage.removeItem('viewedRegistryItem');
        pendingItemId = null;
    }
    
    // Track "View Item" link clicks and image link clicks - show prompt immediately
    const viewItemLinks = document.querySelectorAll('.btn-registry-link, .registry-item-image-link');
    viewItemLinks.forEach(link => {
        link.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const itemTitle = this.getAttribute('data-item-title');
            if (itemId && itemTitle) {
                // Store the viewed item in localStorage
                localStorage.setItem('viewedRegistryItem', JSON.stringify({
                    itemId: itemId,
                    itemTitle: itemTitle,
                    timestamp: Date.now()
                }));
                // Show the prompt immediately
                showReturnPrompt(itemId, itemTitle);
            }
        });
    });
    
    // Check for viewed item on page load and show prompt if returning (for page reloads)
    function checkForViewedItem() {
        const viewedItemData = localStorage.getItem('viewedRegistryItem');
        if (viewedItemData) {
            try {
                const data = JSON.parse(viewedItemData);
                const itemId = data.itemId;
                const itemTitle = data.itemTitle || 'this item';
                showReturnPrompt(itemId, itemTitle);
            } catch (e) {
                // Invalid data, clear it
                localStorage.removeItem('viewedRegistryItem');
            }
        }
    }
    
    // Check on page load (for page reloads)
    checkForViewedItem();
    
    // Also check when page becomes visible again (user returns to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Page is now visible, check if we should show the prompt
            checkForViewedItem();
        }
    });
    
    // Handle return prompt modal actions
    if (returnPromptYes) {
        returnPromptYes.addEventListener('click', function() {
            if (pendingItemId) {
                // Open the purchase modal for this item
                closeReturnPromptModal();
                openModal(pendingItemId);
            }
        });
    }
    
    if (returnPromptNo) {
        returnPromptNo.addEventListener('click', function() {
            closeReturnPromptModal();
        });
    }
    
    if (returnPromptClose) {
        returnPromptClose.addEventListener('click', closeReturnPromptModal);
    }
    
    window.addEventListener('click', function(e) {
        if (e.target === returnPromptModal) {
            closeReturnPromptModal();
        }
    });
    
    // Check for viewed item on page load
    checkForViewedItem();
    
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
                // Clear the viewed item from localStorage if this is the item that was viewed
                const viewedItemData = localStorage.getItem('viewedRegistryItem');
                if (viewedItemData) {
                    try {
                        const data = JSON.parse(viewedItemData);
                        if (data.itemId === itemId) {
                            localStorage.removeItem('viewedRegistryItem');
                        }
                    } catch (e) {
                        // Ignore parse errors
                    }
                }
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
                const mostWantedA = parseInt(a.getAttribute('data-most-wanted')) || 0;
                const mostWantedB = parseInt(b.getAttribute('data-most-wanted')) || 0;

                // Always sort available before purchased
                if (purchasedA !== purchasedB) {
                    return purchasedA - purchasedB;
                }
                // Within available items, most wanted first
                if (mostWantedA !== mostWantedB) {
                    return mostWantedB - mostWantedA;
                }

                switch(sortValue) {
                    case 'price-low':
                        return priceA - priceB;

                    case 'price-high':
                        return priceB - priceA;
                }
            });
            
            // Clear and re-append sorted items
            items.forEach(item => itemsGrid.appendChild(item));
        });
    }
    
    
    // House Fund form submission
    const houseFundForm = document.getElementById('house-fund-form');
    const houseFundMessage = document.getElementById('house-fund-message');
    const houseTotalAmountElement = document.getElementById('house-fund-total-amount');
    
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
                    fund: 'house',
                    amount: amount,
                    contributor_name: contributorName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total amount
                    if (houseTotalAmountElement) {
                        houseTotalAmountElement.textContent = '$' + parseFloat(data.total).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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

    // Honeymoon Fund form submission
    const honeymoonFundForm = document.getElementById('honeymoon-fund-form');
    const honeymoonFundMessage = document.getElementById('honeymoon-fund-message');
    const honeymoonTotalAmountElement = document.getElementById('honeymoon-fund-total-amount');

    if (honeymoonFundForm) {
        honeymoonFundForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(document.getElementById('honeymoon-contribution-amount').value);
            const contributorName = document.getElementById('honeymoon-contributor-name').value.trim();
            
            if (!amount || amount <= 0) {
                showHoneymoonFundMessage('Please enter a valid amount.', 'error');
                return;
            }
            
            // Disable form during submission
            const submitBtn = honeymoonFundForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            fetch('/api/submit-contribution.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    fund: 'honeymoon',
                    amount: amount,
                    contributor_name: contributorName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total amount
                    if (honeymoonTotalAmountElement) {
                        honeymoonTotalAmountElement.textContent = '$' + parseFloat(data.total).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                    // Reset form
                    honeymoonFundForm.reset();
                    showHoneymoonFundMessage(data.message || 'Thank you for your contribution!', 'success');
                } else {
                    showHoneymoonFundMessage(data.error || 'An error occurred. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showHoneymoonFundMessage('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Contribution';
            });
        });
    }

    function showHoneymoonFundMessage(message, type) {
        if (!honeymoonFundMessage) return;
        
        honeymoonFundMessage.textContent = message;
        honeymoonFundMessage.className = 'honeymoon-fund-message ' + (type === 'success' ? 'success' : 'error');
        honeymoonFundMessage.style.display = 'block';
        
        // Hide message after 5 seconds
        setTimeout(() => {
            honeymoonFundMessage.style.display = 'none';
        }, 5000);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>





