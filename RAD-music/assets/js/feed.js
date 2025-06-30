$(document).ready(function() {
    // Handle post submission
    $('#create-post-form').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: '/api/posts.php?action=create',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Prepend new post to feed
                    const postHtml = generatePostHtml(response.post);
                    $('.posts-list').prepend(postHtml);
                    
                    // Reset form
                    $('#create-post-form')[0].reset();
                    $('#media-preview').empty();
                    $('#track-preview').empty();
                }
            }
        });
    });
    
    // Handle media preview
    $('#post-media').change(function() {
        const files = this.files;
        const $preview = $('#media-preview');
        $preview.empty();
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                let previewHtml = '';
                
                if (file.type.startsWith('image/')) {
                    previewHtml = `<img src="${e.target.result}" alt="Preview">`;
                } else if (file.type.startsWith('video/')) {
                    previewHtml = `
                        <video controls>
                            <source src="${e.target.result}" type="${file.type}">
                        </video>
                    `;
                } else if (file.type.startsWith('audio/')) {
                    previewHtml = `
                        <audio controls>
                            <source src="${e.target.result}" type="${file.type}">
                        </audio>
                    `;
                }
                
                $preview.append(`
                    <div class="media-item">
                        ${previewHtml}
                        <button type="button" class="remove-media" data-index="${i}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `);
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Handle remove media
    $('#media-preview').on('click', '.remove-media', function() {
        const index = $(this).data('index');
        const files = $('#post-media')[0].files;
        const newFiles = Array.from(files).filter((_, i) => i !== index);
        
        // Create new FileList (workaround since FileList is read-only)
        const dataTransfer = new DataTransfer();
        newFiles.forEach(file => dataTransfer.items.add(file));
        $('#post-media')[0].files = dataTransfer.files;
        
        // Trigger change event to update preview
        $('#post-media').trigger('change');
    });
    
    // Handle attach music
    $('#attach-music-btn').click(function() {
        showModal('track-search-modal');
    });
    
    // Infinite scroll
    let loading = false;
    let offset = 10; // Initial offset after first 10 posts
    
    $(window).scroll(function() {
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 200 && !loading) {
            loading = true;
            $('.posts-list').append('<div class="loading-spinner">Loading more posts...</div>');
            
            $.get('/api/posts.php', { limit: 10, offset }, function(response) {
                if (response.posts && response.posts.length > 0) {
                    response.posts.forEach(post => {
                        $('.posts-list').append(generatePostHtml(post));
                    });
                    offset += 10;
                }
            }).always(function() {
                $('.loading-spinner').remove();
                loading = false;
            });
        }
    });
});

/**
 * Generates HTML for a music post
 * @param {Object} post - Post data object
 * @param {number} post.id - Post ID
 * @param {string} post.user_avatar - User avatar URL
 * @param {string} post.username - Display name
 * @param {string} post.created_at - ISO timestamp
 * @param {string} post.content - Post text content
 * @param {Object} post.track - Associated track data
 * @param {boolean} [post.is_liked=false] - Like status
 * @param {number} post.like_count - Like count
 * @param {number} post.comment_count - Comment count
 * @returns {string} Generated HTML
 */
function generatePostHtml(post) {
    const formattedTime = new Date(post.created_at).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    return `
    <div class="post card mb-4" data-post-id="${post.id}">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <img src="${post.user_avatar || '/images/default-avatar.jpg'}" 
                     alt="${post.username}'s avatar"
                     class="rounded-circle me-3" 
                     width="50" 
                     height="50">
                
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">${escapeHtml(post.username)}</h5>
                        <small class="text-muted">${formattedTime}</small>
                    </div>
                    
                    <p class="card-text">${escapeHtml(post.content)}</p>
                    
                    ${post.track ? `
                    <div class="track-preview mt-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <img src="${post.track.cover_url}" 
                                 alt="${post.track.title} cover"
                                 width="40" 
                                 height="40"
                                 class="me-3">
                            <div>
                                <strong>${escapeHtml(post.track.title)}</strong>
                                <div class="text-muted small">${escapeHtml(post.track.artist)}</div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="post-actions mt-3 d-flex justify-content-between">
                        <button class="btn btn-sm btn-outline-danger like-btn ${post.is_liked ? 'active' : ''}"
                                aria-label="Like post">
                            ♥ ${post.like_count}
                        </button>
                        <button class="btn btn-sm btn-outline-secondary comment-btn"
                                aria-label="Comment">
                            💬 ${post.comment_count}
                        </button>
                        <button class="btn btn-sm btn-outline-primary share-btn"
                                aria-label="Share">
                            ↪ Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
}

// Helper function to prevent XSS
function escapeHtml(unsafe) {
    return unsafe?.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;") || '';
}