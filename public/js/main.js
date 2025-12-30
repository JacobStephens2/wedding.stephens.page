// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileNav = document.querySelector('.mobile-nav');
    
    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', function() {
            menuToggle.classList.toggle('active');
            mobileNav.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        const mobileLinks = mobileNav.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                menuToggle.classList.remove('active');
                mobileNav.classList.remove('active');
            });
        });
    }
    
    // Lightbox functionality for story page images
    const clickableImages = document.querySelectorAll('.clickable-image');
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxClose = document.querySelector('.lightbox-close');
    
    // Store current carousel and index for keyboard navigation
    let currentLightboxCarousel = null;
    let currentLightboxIndex = 0;
    let currentLightboxImages = [];
    
    if (clickableImages.length > 0 && lightbox && lightboxImage) {
        // Function to get all images in a carousel or standalone images
        function getImagesForLightbox(clickedImage) {
            if (clickedImage.classList.contains('carousel-image')) {
                const carousel = clickedImage.closest('.photo-carousel');
                if (carousel) {
                    return Array.from(carousel.querySelectorAll('.carousel-image'));
                }
            }
            // For standalone images, return just that image
            return [clickedImage];
        }
        
        // Function to find the index of an image in its carousel
        function getImageIndex(image, images) {
            return images.indexOf(image);
        }
        
        // Function to open lightbox with a specific image
        function openLightbox(image, images, index) {
            lightboxImage.src = image.src;
            lightboxImage.alt = image.alt;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Store current state for keyboard navigation
            currentLightboxImages = images;
            currentLightboxIndex = index;
            currentLightboxCarousel = image.closest('.photo-carousel');
        }
        
        // Function to navigate to next/previous image in lightbox
        function navigateLightbox(direction) {
            if (currentLightboxImages.length === 0) return;
            
            if (direction === 'next') {
                currentLightboxIndex = (currentLightboxIndex + 1) % currentLightboxImages.length;
            } else if (direction === 'prev') {
                currentLightboxIndex = (currentLightboxIndex - 1 + currentLightboxImages.length) % currentLightboxImages.length;
            }
            
            const newImage = currentLightboxImages[currentLightboxIndex];
            lightboxImage.src = newImage.src;
            lightboxImage.alt = newImage.alt;
        }
        
        // Open lightbox when clicking an image
        clickableImages.forEach(img => {
            img.addEventListener('click', function() {
                // If this is a carousel image, find the active image in the carousel
                let imageToShow = this;
                if (this.classList.contains('carousel-image')) {
                    const carousel = this.closest('.photo-carousel');
                    if (carousel) {
                        const activeImage = carousel.querySelector('.carousel-image.active');
                        if (activeImage) {
                            imageToShow = activeImage;
                        }
                    }
                }
                
                const images = getImagesForLightbox(imageToShow);
                const index = getImageIndex(imageToShow, images);
                openLightbox(imageToShow, images, index);
            });
        });
        
        // Close lightbox functions
        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
            currentLightboxCarousel = null;
            currentLightboxIndex = 0;
            currentLightboxImages = [];
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
        
        // Keyboard navigation: Escape to close, Arrow keys to navigate
        document.addEventListener('keydown', function(e) {
            if (!lightbox.classList.contains('active')) return;
            
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowRight') {
                navigateLightbox('next');
            } else if (e.key === 'ArrowLeft') {
                navigateLightbox('prev');
            }
        });
        
        // Touch/swipe navigation for mobile devices
        let touchStartX = 0;
        let touchEndX = 0;
        const minSwipeDistance = 50; // Minimum distance in pixels to trigger a swipe
        
        lightbox.addEventListener('touchstart', function(e) {
            if (!lightbox.classList.contains('active')) return;
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        lightbox.addEventListener('touchend', function(e) {
            if (!lightbox.classList.contains('active')) return;
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            const swipeDistance = touchEndX - touchStartX;
            
            // Swipe right (next image) - user swiped left to right
            if (swipeDistance > minSwipeDistance) {
                navigateLightbox('next');
            }
            // Swipe left (previous image) - user swiped right to left
            else if (swipeDistance < -minSwipeDistance) {
                navigateLightbox('prev');
            }
        }
    }
    
    // Photo carousel functionality - handle multiple carousels
    const photoCarousels = document.querySelectorAll('.photo-carousel');
    photoCarousels.forEach(photoCarousel => {
        const images = photoCarousel.querySelectorAll('.carousel-image');
        const prevBtn = photoCarousel.querySelector('.carousel-prev');
        const nextBtn = photoCarousel.querySelector('.carousel-next');
        const indicators = photoCarousel.querySelectorAll('.indicator');
        let currentIndex = 0;
        
        function showSlide(index) {
            // Remove active class from all images and indicators in this carousel
            images.forEach(img => img.classList.remove('active'));
            indicators.forEach(ind => ind.classList.remove('active'));
            
            // Add active class to current slide
            images[index].classList.add('active');
            indicators[index].classList.add('active');
            currentIndex = index;
        }
        
        function nextSlide() {
            const nextIndex = (currentIndex + 1) % images.length;
            showSlide(nextIndex);
        }
        
        function prevSlide() {
            const prevIndex = (currentIndex - 1 + images.length) % images.length;
            showSlide(prevIndex);
        }
        
        // Button event listeners
        if (nextBtn) {
            nextBtn.addEventListener('click', nextSlide);
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', prevSlide);
        }
        
        // Indicator event listeners
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => showSlide(index));
        });
    });
});

