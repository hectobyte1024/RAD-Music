/**
 * RAD Music Network - Home Page Interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components based on auth state
    const isLoggedIn = document.body.classList.contains('logged-in');
    
    if (isLoggedIn) {
        initLoggedInFeatures();
    } else {
        initGuestFeatures();
    }
    
    // Common initialization
    initAudioPreviews();
    initTooltips();
});

/**
 * Features for logged-in users
 */
function initLoggedInFeatures() {
    // Infinite scroll for activity feed
    const activityFeed = document.querySelector('.activity-feed');
    if (activityFeed) {
        let loading = false;
        let page = 1;
        
        window.addEventListener('scroll', throttle(() => {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500 && !loading) {
                loadMoreActivities();
            }
        }, 200));
        
        async function loadMoreActivities() {
            loading = true;
            showLoadingIndicator();
            
            try {
                const response = await fetch(`/api/activities?page=${++page}`);
                const activities = await response.json();
                
                if (activities.length > 0) {
                    activities.forEach(activity => {
                        activityFeed.appendChild(createActivityElement(activity));
                    });
                } else {
                    window.removeEventListener('scroll', loadMoreActivities);
                    showEndOfFeedMessage();
                }
            } catch (error) {
                console.error('Failed to load activities:', error);
                showError('Failed to load more activities');
            } finally {
                hideLoadingIndicator();
                loading = false;
            }
        }
    }
    
    // Track preview interactions
    document.querySelectorAll('.track-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.track-actions')) {
                const trackId = this.dataset.trackId;
                playTrackPreview(trackId);
            }
        });
    });
}

/**
 * Features for guest users
 */
function initGuestFeatures() {
    // Animate feature cards on scroll
    const features = document.querySelectorAll('.feature');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    }, { threshold: 0.1 });
    
    features.forEach(feature => observer.observe(feature));
    
    // Hero button hover effects
    const heroButtons = document.querySelectorAll('.hero-actions .btn');
    heroButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.classList.add('animate__animated', 'animate__pulse');
        });
        button.addEventListener('animationend', () => {
            button.classList.remove('animate__animated', 'animate__pulse');
        });
    });
}

/**
 * Audio preview functionality
 */
function initAudioPreviews() {
    // Setup Web Audio API context
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    let currentAudioSource = null;
    
    window.playTrackPreview = function(trackId) {
        // Stop currently playing preview
        if (currentAudioSource) {
            currentAudioSource.stop();
        }
        
        // Visual feedback
        document.querySelectorAll('.track-card').forEach(card => {
            card.classList.remove('playing');
        });
        document.querySelector(`.track-card[data-track-id="${trackId}"]`).classList.add('playing');
        
        // Fetch and play preview
        fetch(`/api/tracks/${trackId}/preview`)
            .then(response => response.arrayBuffer())
            .then(buffer => audioContext.decodeAudioData(buffer))
            .then(audioBuffer => {
                currentAudioSource = audioContext.createBufferSource();
                currentAudioSource.buffer = audioBuffer;
                currentAudioSource.connect(audioContext.destination);
                currentAudioSource.start();
                
                currentAudioSource.onended = () => {
                    document.querySelector(`.track-card[data-track-id="${trackId}"]`)
                        .classList.remove('playing');
                };
            })
            .catch(error => {
                console.error('Error playing preview:', error);
                showError('Could not play track preview');
            });
    };
}

/**
 * Create activity feed item element
 */
function createActivityElement(activity) {
    const element = document.createElement('div');
    element.className = 'activity-item';
    element.innerHTML = `
        <img src="${activity.actor_avatar || '/assets/images/default-avatar.jpg'}" alt="User">
        <div class="activity-content">
            <p>
                <strong>${escapeHtml(activity.actor_username)}</strong>
                ${getActivityDescription(activity)}
            </p>
            <small>${timeAgo(activity.created_at)}</small>
        </div>
    `;
    return element;
}

/**
 * Helper functions
 */
function throttle(func, limit) {
    let lastFunc;
    let lastRan;
    return function() {
        const context = this;
        const args = arguments;
        if (!lastRan) {
            func.apply(context, args);
            lastRan = Date.now();
        } else {
            clearTimeout(lastFunc);
            lastFunc = setTimeout(function() {
                if ((Date.now() - lastRan) >= limit) {
                    func.apply(context, args);
                    lastRan = Date.now();
                }
            }, limit - (Date.now() - lastRan));
        }
    };
}

function escapeHtml(unsafe) {
    return unsafe?.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;") || '';
}

function timeAgo(timestamp) {
    // Implement your time ago function or use a library
    return new Date(timestamp).toLocaleString();
}

function getActivityDescription(activity) {
    // Match your PHP implementation
    const types = {
        'track_like': 'liked a track',
        'new_post': 'shared a new post',
        'new_comment': 'commented on a post'
    };
    return types[activity.type] || 'performed an action';
}

function showLoadingIndicator() {
    // Implement loading indicator
}

function hideLoadingIndicator() {
    // Hide loading indicator
}

function showEndOfFeedMessage() {
    // Show "No more activities" message
}

function showError(message) {
    // Show error message to user
}

function initTooltips() {
    // Initialize tooltips using your preferred library
    // Example with Bootstrap:
    // [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    //   .forEach(el => new bootstrap.Tooltip(el));
}