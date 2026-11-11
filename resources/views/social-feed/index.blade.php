@extends($currentUser->role === 'super_admin' ? 'layouts.superadmin' : ($currentUser->role === 'admin' ? 'layouts.admin' : 'layouts.user'))

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/social_feed/social_feed.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/post-composer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/post-card.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/comments-panel.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/modal-comments.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/image_gallery.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/feed-navigation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/components/utilities.css') }}">
    <link rel="stylesheet" href="{{ asset('css/social_feed/variables.css') }}">
@endsection

@section('content')
<div class="head-title">
    <div class="left">
        <h1>Social Feed</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#">Social Feed</a></li>
        </ul>
    </div>
</div>

<div class="feed-container">
    <!-- TOP ROW - Navigation -->
    <div class="feed-navigation">
        <div class="feed-tabs">
            <button class="feed-tab active" data-filter="all">
                <i class='bx bx-home'></i> All Posts
            </button>
            <button class="feed-tab" data-filter="my_posts">
                <i class='bx bx-user'></i> My Posts
            </button>
            <button class="feed-tab" data-filter="department">
                <i class='bx bx-buildings'></i> Departments
            </button>
            <button class="feed-tab" data-filter="pinned">
                <i class='bx bx-pin'></i> Pinned
            </button>
        </div>
    </div>

    <!-- CONTENT AREA - Single column layout (no side panel) -->
    <div class="feed-content-area" id="feedContentArea">
        <!-- Main Column - Posts and Composer -->
        <div class="feed-main-column">
            <!-- Post Composer -->
            <div class="post-composer">
                <form id="postForm" onsubmit="createPost(event)">
                    <div class="composer-header">
                        <img src="{{ $currentUser->profile_image_url ?? asset('img/cvsu-logo.png') }}"
                            alt="Your avatar" class="composer-avatar" id="composerAvatar">

                        <div class="composer-user-info">
                            <strong id="composerName">{{ $currentUser->full_name }}</strong>

                            <div class="visibility-selector">
                                <button type="button" class="visibility-btn" id="visibilityBtn">
                                    <i class='bx bx-globe'></i>
                                    <span id="visibilityText">Everyone</span>
                                    <i class='bx bx-chevron-down'></i>
                                </button>

                                <!-- Main Visibility Dropdown -->
                                <div class="visibility-dropdown" id="visibilityDropdown">
                                    <div class="visibility-option" data-visibility="public">
                                        <i class='bx bx-globe'></i>
                                        <div>
                                            <strong>Everyone</strong>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                Visible to all users
                                            </div>
                                        </div>
                                    </div>
                                    <div class="visibility-option" data-visibility="department">
                                        <i class='bx bx-buildings'></i>
                                        <div>
                                            <strong>Department</strong>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                Choose specific departments
                                            </div>
                                        </div>
                                        <i class='bx bx-chevron-right' style="margin-left: auto; color: var(--text-secondary);"></i>
                                    </div>
                                    <div class="visibility-option" data-visibility="custom">
                                        <i class='bx bx-group'></i>
                                        <div>
                                            <strong>Specific Users</strong>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                Choose who can see
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Department Selector Panel -->
                                <div class="department-selector" id="departmentSelector">
                                    <div class="department-selector-header">
                                        <h4>Select Departments</h4>
                                        <button type="button" class="back-btn" onclick="showVisibilityDropdown()">
                                            <i class='bx bx-arrow-back'></i>
                                        </button>
                                    </div>

                                    <div class="department-options">
                                        @foreach ($departments as $dept)
                                            <div class="department-option" onclick="toggleDepartment('{{ $dept->department_code }}', this.querySelector('.department-checkbox'))">
                                                <div class="department-checkbox"></div>
                                                <div class="department-info">
                                                    <strong>{{ $dept->department_code }}</strong>
                                                    <small>{{ $dept->department_name }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="department-actions">
                                        <button type="button" class="select-all-btn" onclick="toggleSelectAllDepartments()">
                                            <span id="selectAllText">Select All</span>
                                        </button>
                                        <button type="button" class="department-confirm-btn" onclick="confirmDepartmentSelection()" disabled>
                                            Confirm Selection
                                        </button>
                                    </div>
                                </div>

                                <div class="selected-departments-display" id="selectedDepartmentsDisplay"></div>
                            </div>
                        </div>
                    </div>

                    <textarea class="composer-textarea" id="postContent"
                        placeholder="What's happening in your department?" required></textarea>

                    <div class="composer-actions">
                        <div class="composer-tools">
                            <button type="button" class="composer-tool" title="Add Image"
                                onclick="document.getElementById('imageInput').click()">
                                <i class='bx bx-image'></i>
                            </button>
                            <button type="button" class="composer-tool" title="Add File"
                                onclick="document.getElementById('fileInput').click()">
                                <i class='bx bx-paperclip'></i>
                            </button>
                        </div>

                        <button type="submit" class="post-btn" id="postBtn" disabled>
                            <i class='bx bx-send'></i> Post
                        </button>
                    </div>

                    <!-- Hidden Inputs -->
                    <input type="file" id="imageInput" accept="image/*" multiple style="display: none;"
                        onchange="handleImageUpload(this)">
                    <input type="file" id="fileInput" multiple style="display: none;"
                        onchange="handleFileUpload(this)">
                    <input type="hidden" id="postVisibility" value="public">
                    <input type="hidden" id="selectedDepartments" value="">
                </form>
            </div>
            <!-- Posts Feed -->
            <div class="feed-content" id="feedContent">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Loading posts...</p>
                </div>
            </div>

            <!-- Load More -->
            <div class="load-more" id="loadMoreSection" style="display: none;">
                <button class="load-more-btn" onclick="loadMorePosts()">
                    <i class='bx bx-refresh'></i> Load More Posts
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <!-- Social Feed Modules -->
    <script src="{{ asset('js/social_feed/utilities.js') }}"></script>
    <script src="{{ asset('js/social_feed/core.js') }}"></script>
    <script src="{{ asset('js/social_feed/post_composer.js') }}"></script>
    <script src="{{ asset('js/social_feed/visibility_selector.js') }}"></script>
    <script src="{{ asset('js/social_feed/post_renderer.js') }}"></script>
    <script src="{{ asset('js/social_feed/post_actions.js') }}"></script>
    <script src="{{ asset('js/social_feed/comment_system.js') }}"></script>
    <script src="{{ asset('js/social_feed/media_handler.js') }}"></script>
    <script src="{{ asset('js/social_feed/main.js') }}"></script>

    <!-- Page-specific initialization -->
    <script>
        // Override the toggleComments function to use modal instead of side panel
        function toggleComments(postId) {
            if (window.commentSystem && window.commentSystem.openCommentsModal) {
                window.commentSystem.openCommentsModal(postId);
            } else {
                console.warn('Comment system not ready, trying again...');
                setTimeout(() => {
                    if (window.commentSystem && window.commentSystem.openCommentsModal) {
                        window.commentSystem.openCommentsModal(postId);
                    }
                }, 100);
            }
        }

        // Set active sidebar item and page title for social feed
        document.addEventListener('DOMContentLoaded', function () {
            // Set active sidebar item
            if (window.AppUtils) {
                window.AppUtils.setActiveSidebarItem('social-feed');
                window.AppUtils.updateNavbarTitle('Social Feed');
            }

            // Initialize search for social feed if needed
            const navbarSearchForm = document.getElementById('navbarSearchForm');
            if (navbarSearchForm) {
                navbarSearchForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const searchInput = document.getElementById('navbarSearchInput');
                    if (searchInput && searchInput.value.trim()) {
                        if (window.searchPosts) {
                            window.searchPosts(e);
                        }
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const visibilityBtn = document.getElementById('visibilityBtn');
            const visibilityDropdown = document.getElementById('visibilityDropdown');

            if (visibilityBtn && visibilityDropdown && !window.visibilitySelector) {
                visibilityBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    visibilityDropdown.classList.toggle('show');
                });
            }
        });
    </script>
@endpush