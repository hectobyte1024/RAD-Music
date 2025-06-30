// Global AJAX setup
$.ajaxSetup({
    beforeSend: function(xhr) {
        if ($('meta[name="csrf-token"]').length) {
            xhr.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
        }
    }
});

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Handle CSRF token for AJAX requests
let csrfToken = $('meta[name="csrf-token"]').attr('content');

// Mobile menu toggle
$('.mobile-menu-btn').click(function() {
    $('.navbar-links').toggleClass('active');
});

// Handle logout
$('#logout-btn').click(function(e) {
    e.preventDefault();
    $.post('/api/auth.php', { action: 'logout' }, function() {
        window.location.href = '/RAD-music/templates/login.php';
    });
});

// Initialize audio player
let audioPlayer = new Audio();
let currentlyPlaying = null;

$('body').on('click', '.play-btn', function() {
    const previewUrl = $(this).data('preview-url');
    if (!previewUrl) return;
    
    if (currentlyPlaying === previewUrl) {
        // Toggle play/pause
        if (audioPlayer.paused) {
            audioPlayer.play();
            $(this).html('<i class="fas fa-pause"></i>');
        } else {
            audioPlayer.pause();
            $(this).html('<i class="fas fa-play"></i>');
        }
    } else {
        // Play new track
        currentlyPlaying = previewUrl;
        audioPlayer.src = previewUrl;
        audioPlayer.play();
        $('.play-btn').html('<i class="fas fa-play"></i>');
        $(this).html('<i class="fas fa-pause"></i>');
    }
});

audioPlayer.addEventListener('ended', function() {
    $('.play-btn').html('<i class="fas fa-play"></i>');
    currentlyPlaying = null;
});

// Handle like buttons
$('body').on('click', '.like-btn:not(.disabled)', function() {
    const $btn = $(this);
    $btn.addClass('disabled');
    
    const postId = $btn.closest('.post').data('post-id');
    const isLiked = $btn.hasClass('liked');
    
    $.post('/api/posts.php?action=like', { postId }, function(response) {
        if (response.success) {
            $btn.toggleClass('liked');
            $btn.find('span').text(response.likeCount);
        }
    }).always(function() {
        $btn.removeClass('disabled');
    });
});

// Handle comment submission
$('body').on('submit', '.add-comment-form', function(e) {
    e.preventDefault();
    const $form = $(this);
    const $input = $form.find('.comment-input');
    const content = $input.val().trim();
    
    if (content) {
        const postId = $form.closest('.post').data('post-id');
        
        $.post('/api/posts.php?action=comment', { postId, content }, function(response) {
            if (response.success) {
                // Add new comment to the list
                const commentHtml = `
                    <div class="comment">
                        <img src="${response.comment.avatar_url || '/assets/images/default-avatar.jpg'}" alt="Profile">
                        <div class="comment-content">
                            <h5>${response.comment.username}</h5>
                            <p>${response.comment.content}</p>
                            <small>just now</small>
                        </div>
                    </div>
                `;
                
                $form.before(commentHtml);
                $input.val('');
                
                // Update comment count
                const $commentBtn = $form.closest('.post').find('.comment-btn span');
                $commentBtn.text(parseInt($commentBtn.text()) + 1);
            }
        });
    }
});

// Initialize modals
$('.modal').each(function() {
    const $modal = $(this);
    
    // Close modal when clicking outside content
    $modal.click(function(e) {
        if (e.target === this) {
            $modal.removeClass('active');
        }
    });
    
    // Close button
    $modal.find('.modal-close').click(function() {
        $modal.removeClass('active');
    });
});

// Show modal
function showModal(modalId) {
    $(`#${modalId}`).addClass('active');
}

// Hide modal
function hideModal(modalId) {
    $(`#${modalId}`).removeClass('active');
}