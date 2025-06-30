/**
 * RAD Music - Profile Page Controller
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const profileId = document.querySelector('.profile-header').dataset.profileId;
    const isCurrentUser = document.body.classList.contains('current-user');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const editProfileModal = document.getElementById('edit-profile-modal');
    const editProfileForm = document.getElementById('edit-profile-form');
    const followButton = document.getElementById('follow-btn');
    const postsGrid = document.querySelector('.posts-grid');

    // Initialize tab switching
    initTabs();

    // Initialize modals
    if (isCurrentUser) {
        initProfileModals();
    }

    // Initialize follow button if not current user
    if (followButton) {
        initFollowButton();
    }

    // Initialize infinite scroll for posts
    if (postsGrid) {
        initInfiniteScroll();
    }

    /**
     * Initialize tab switching functionality
     */
    function initTabs() {
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                const tabName = this.dataset.tab;
                document.getElementById(`${tabName}-tab`).classList.add('active');

                // Lazy load content when tab is activated
                loadTabContent(tabName);
            });
        });
    }

    /**
     * Load content for a tab when activated
     */
    async function loadTabContent(tabName) {
        const tabContent = document.getElementById(`${tabName}-tab`);
        
        // Only load if empty and not already loading
        if (tabContent.innerHTML.trim() === '' && !tabContent.dataset.loading) {
            tabContent.dataset.loading = true;
            tabContent.innerHTML = '<div class="loading-spinner"></div>';

            try {
                const response = await fetch(`/api/profile/${profileId}/${tabName}`);
                const data = await response.json();

                if (data.success) {
                    tabContent.innerHTML = renderTabContent(tabName, data.content);
                } else {
                    tabContent.innerHTML = `<div class="error-message">${data.message}</div>`;
                }
            } catch (error) {
                console.error(`Error loading ${tabName}:`, error);
                tabContent.innerHTML = '<div class="error-message">Failed to load content</div>';
            } finally {
                tabContent.dataset.loading = false;
            }
        }
    }

    /**
     * Render content for different tabs
     */
    function renderTabContent(tabName, content) {
        switch (tabName) {
            case 'likes':
                return content.length > 0 
                    ? content.map(post => generatePostHtml(post)).join('')
                    : '<div class="empty-state"><i class="fas fa-heart"></i><h3>No liked posts</h3></div>';

            case 'tracks':
                return content.length > 0
                    ? `<div class="tracks-grid">${
                        content.map(track => generateTrackHtml(track)).join('')
                      }</div>`
                    : '<div class="empty-state"><i class="fas fa-music"></i><h3>No tracks uploaded</h3></div>';

            default:
                return '';
        }
    }

    /**
     * Initialize profile editing modals
     */
    function initProfileModals() {
        // Edit Profile Button
        document.getElementById('edit-profile-btn').addEventListener('click', () => {
            editProfileModal.style.display = 'block';
        });

        // Change Cover Button
        document.getElementById('change-cover-btn').addEventListener('click', () => {
            // Implement cover photo upload
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.click();

            input.addEventListener('change', async (e) => {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    await uploadCoverPhoto(file);
                }
            });
        });

        // Cancel Edit Button
        document.getElementById('cancel-edit-btn').addEventListener('click', () => {
            editProfileModal.style.display = 'none';
        });

        // Edit Profile Form Submission
        editProfileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await updateProfile();
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === editProfileModal) {
                editProfileModal.style.display = 'none';
            }
        });
    }

    /**
     * Update profile information
     */
    async function updateProfile() {
        const formData = new FormData(editProfileForm);
        const submitButton = editProfileForm.querySelector('button[type="submit"]');
        
        try {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner"></span> Saving...';

            const response = await fetch('/api/profile/update', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCSRFToken()
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Update UI with new profile data
                document.querySelector('.profile-info h1').textContent = data.username;
                document.querySelector('.profile-bio').textContent = data.bio || '';
                document.querySelector('.profile-location').innerHTML = data.location 
                    ? `<i class="fas fa-map-marker-alt"></i> ${data.location}`
                    : '';
                
                // Close modal
                editProfileModal.style.display = 'none';
                showToast('Profile updated successfully');
            } else {
                showError(data.message || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            showError('Network error. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Save Changes';
        }
    }

    /**
     * Upload new cover photo
     */
    async function uploadCoverPhoto(file) {
        try {
            const formData = new FormData();
            formData.append('cover', file);

            const response = await fetch('/api/profile/cover', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCSRFToken()
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Update cover photo in UI
                document.querySelector('.profile-header').style.backgroundImage = `url('${data.cover_url}')`;
                showToast('Cover photo updated successfully');
            } else {
                showError(data.message || 'Failed to update cover photo');
            }
        } catch (error) {
            console.error('Error uploading cover photo:', error);
            showError('Failed to upload cover photo');
        }
    }

    /**
     * Initialize follow button functionality
     */
    function initFollowButton() {
        followButton.addEventListener('click', async function() {
            const isFollowing = this.textContent.trim() === 'Following';
            const action = isFollowing ? 'unfollow' : 'follow';
            
            try {
                this.disabled = true;
                this.innerHTML = '<span class="spinner"></span>';

                const response = await fetch(`/api/user/${action}/${profileId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCSRFToken()
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Update button state
                    this.textContent = isFollowing ? 'Follow' : 'Following';
                    this.classList.toggle('btn-primary');
                    this.classList.toggle('btn-outline');
                    
                    // Update followers count
                    document.querySelector('.profile-stats div:nth-child(2) strong').textContent = data.followers;
                } else {
                    showError(data.message || `Failed to ${action} user`);
                }
            } catch (error) {
                console.error(`Error ${action}ing user:`, error);
                showError(`Failed to ${action} user`);
            } finally {
                this.disabled = false;
            }
        });
    }

    /**
     * Initialize infinite scroll for posts
     */
    function initInfiniteScroll() {
        let loading = false;
        let page = 1;
        let hasMore = true;

        window.addEventListener('scroll', throttle(async () => {
            if (loading || !hasMore) return;

            const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
            const threshold = 100; // pixels from bottom

            if (scrollTop + clientHeight >= scrollHeight - threshold) {
                loading = true;
                showLoadingIndicator();

                try {
                    const response = await fetch(`/api/profile/posts/${profileId}?page=${++page}`);
                    const data = await response.json();

                    if (data.posts.length > 0) {
                        postsGrid.innerHTML += data.posts.map(post => generatePostHtml(post)).join('');
                    } else {
                        hasMore = false;
                    }
                } catch (error) {
                    console.error('Error loading more posts:', error);
                } finally {
                    loading = false;
                    hideLoadingIndicator();
                }
            }
        }, 200));
    }

    /**
     * Generate HTML for a post (similar to your PHP partial)
     */
    function generatePostHtml(post) {
        return `
        <div class="post" data-post-id="${post.id}">
            <div class="post-header">
                <img src="${post.user_avatar || '/assets/images/default-avatar.jpg'}" alt="Profile">
                <div>
                    <h4>${post.username}</h4>
                    <small>${new Date(post.created_at).toLocaleString()}</small>
                </div>
            </div>
            <div class="post-content">${post.content}</div>
            ${post.track ? `
            <div class="post-track">
                <img src="${post.track.cover_url}" alt="Album cover">
                <div>
                    <h5>${post.track.title}</h5>
                    <p>${post.track.artist}</p>
                </div>
            </div>
            ` : ''}
            <div class="post-actions">
                <button class="like-btn ${post.is_liked ? 'liked' : ''}">
                    <i class="fas fa-heart"></i> ${post.like_count}
                </button>
                <button class="comment-btn">
                    <i class="fas fa-comment"></i> ${post.comment_count}
                </button>
                <button class="share-btn">
                    <i class="fas fa-share"></i>
                </button>
            </div>
        </div>
        `;
    }

    /**
     * Generate HTML for a track
     */
    function generateTrackHtml(track) {
        return `
        <div class="track-card" data-track-id="${track.id}">
            <img src="${track.cover_url}" alt="Album cover">
            <div class="track-info">
                <h4>${track.title}</h4>
                <p>${track.artist}</p>
                <div class="track-stats">
                    <span><i class="fas fa-play"></i> ${track.play_count}</span>
                    <span><i class="fas fa-heart"></i> ${track.like_count}</span>
                </div>
            </div>
            <button class="play-btn"><i class="fas fa-play"></i></button>
        </div>
        `;
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

    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function showToast(message) {
        // Implement toast notification
    }

    function showError(message) {
        // Implement error display
    }

    function showLoadingIndicator() {
        // Implement loading indicator
    }

    function hideLoadingIndicator() {
        // Hide loading indicator
    }
});