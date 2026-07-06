(function() {
    'use strict';

    const PDM = {
        FOLDER_MAX_EXPANDED_DEPTH: 3,
        state: {
            currentFolder: null,
            folders: [],
            files: [],
            folderTree: [],
            breadcrumb: [],
            searchQuery: '',
            isSearchMode: false,
            selectedFile: null,
            selectedFolder: null,
            draggedItem: null,
            viewMode: 'grid',
            sortBy: 'display_name',
            sortOrder: 'ASC',
            isLoading: false,
            sidebarOpen: false,
            detailsOpen: false,
            storageStats: null,
            permissions: null,
            _loadSeq: 0,
            pagination: {
                page: 1,
                perPage: Number(mstvConfig.browserPerPage || 50),
                totalItems: 0,
                totalPages: 0,
                hasPrev: false,
                hasNext: false,
                fromItem: 0,
                toItem: 0,
            },
        },

        elements: {},

        init() {
            this.state.pagination.perPage = this.getStoredBrowserPerPage();
            this.cacheElements();

            if (!this.elements.content || !this.elements.folderTree || !this.elements.details) {
                return;
            }

            this._boundHideContextMenu = this.hideContextMenu.bind(this);
            this.syncPerPageSelect();
            this.syncSortOrderButton();
            this.bindEvents();
            this.loadBrowser();
        },

        isSidebarDrawerViewport() {
            return window.innerWidth <= 992;
        },

        isDetailsDrawerViewport() {
            return window.innerWidth <= 1200;
        },

        cacheElements() {
            this.elements = {
                sidebar: document.getElementById('pdm-sidebar'),
                sidebarToggle: document.getElementById('pdm-sidebar-toggle'),
                folderTree: document.getElementById('pdm-folder-tree'),
                searchInput: document.getElementById('pdm-search-input'),
                newFolderBtn: document.getElementById('pdm-new-folder-btn'),
                breadcrumb: document.getElementById('pdm-breadcrumb'),
                content: document.getElementById('pdm-content'),
                sortSelect: document.getElementById('pdm-sort-select'),
                perPageSelect: document.getElementById('pdm-per-page-select'),
                sortOrderBtn: document.getElementById('pdm-sort-order'),
                viewGridBtn: document.getElementById('pdm-view-grid'),
                viewListBtn: document.getElementById('pdm-view-list'),
                uploadBtn: document.getElementById('pdm-upload-btn'),
                exportBtn: document.getElementById('pdm-export-btn'),
                uploadOverlay: document.getElementById('pdm-upload-overlay'),
                fileInput: document.getElementById('pdm-file-input'),
                details: document.getElementById('pdm-details'),
                modal: document.getElementById('pdm-modal'),
                modalTitle: document.getElementById('pdm-modal-title'),
                modalBody: document.getElementById('pdm-modal-body'),
                modalFooter: document.getElementById('pdm-modal-footer'),
                toastContainer: document.getElementById('pdm-toast-container'),
                previewModal: document.getElementById('pdm-preview-modal'),
                previewContainer: document.getElementById('pdm-preview-container'),
                previewDownload: document.getElementById('pdm-preview-download'),
                filtersToggle: document.getElementById('pdm-filters-toggle'),
                filtersDropdown: document.getElementById('pdm-filters-dropdown'),
                filtersSort: document.querySelector('.pdm-filters-sort'),
                filtersPerPage: document.querySelector('.pdm-filters-per-page'),
                backdrop: null,
            };

            this.createBackdrop();
        },

        createBackdrop() {
            const existingBackdrop = document.getElementById('pdm-backdrop');
            if (existingBackdrop) {
                this.elements.backdrop = existingBackdrop;
                return;
            }

            const backdrop = document.createElement('div');
            backdrop.id = 'pdm-backdrop';
            backdrop.className = 'pdm-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);
            this.elements.backdrop = backdrop;
        },

        bindEvents() {
            this.elements.sidebarToggle?.addEventListener('click', () => this.toggleSidebar());
            
            const sidebarHeader = this.elements.sidebar?.querySelector('.pdm-sidebar-header');
            sidebarHeader?.addEventListener('click', (e) => {
                if (e.target.closest('.pdm-btn')) return;
                if (!this.isSidebarDrawerViewport()) return;
                this.toggleSidebar();
            });

            this.elements.newFolderBtn?.addEventListener('click', () => this.showNewFolderModal());
            this.elements.searchInput?.addEventListener('input', this.debounce((e) => this.search(e.target.value), 300));
            this.elements.sortSelect?.addEventListener('change', (e) => this.updateSort(e.target.value));
            this.elements.perPageSelect?.addEventListener('change', (e) => this.updatePerPage(e.target.value));
            this.elements.sortOrderBtn?.addEventListener('click', () => this.toggleSortOrder());
            this.elements.viewGridBtn?.addEventListener('click', () => this.setViewMode('grid'));
            this.elements.viewListBtn?.addEventListener('click', () => this.setViewMode('list'));
            this.elements.uploadBtn?.addEventListener('click', () => this.showUploadOverlay());
            this.elements.exportBtn?.addEventListener('click', () => this.showExportModal());
            this.elements.uploadOverlay?.addEventListener('click', (e) => {
                if (e.target === this.elements.uploadOverlay) this.hideUploadOverlay();
            });
            this.elements.fileInput?.addEventListener('change', (e) => this.handleFileSelect(e.target.files));

            this.elements.uploadOverlay?.querySelector('.pdm-btn-primary')?.addEventListener('click', () => {
                this.elements.fileInput?.click();
            });

            document.querySelectorAll('.pdm-modal-close, .pdm-modal-backdrop').forEach(el => {
                el.addEventListener('click', () => this.hideModal());
            });

            this.elements.previewModal?.querySelector('.pdm-preview-backdrop')?.addEventListener('click', () => this.hidePreview());
            this.elements.previewModal?.querySelector('.pdm-preview-close')?.addEventListener('click', () => this.hidePreview());

            this.elements.backdrop?.addEventListener('click', () => this.closeMobilePanels());

            this.elements.filtersToggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleFiltersDropdown();
            });

            this.elements.filtersSort?.addEventListener('change', (e) => {
                this.updateSort(e.target.value);
                if (this.elements.sortSelect) {
                    this.elements.sortSelect.value = e.target.value;
                }
            });

            this.elements.filtersPerPage?.addEventListener('change', (e) => {
                this.updatePerPage(e.target.value);
                if (this.elements.perPageSelect) {
                    this.elements.perPageSelect.value = e.target.value;
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeMobilePanels();
                    this.elements.filtersDropdown?.classList.remove('active');
                    this.hideModal();
                    this.hidePreview();
                    this.hideUploadOverlay();
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.pdm-toolbar-filters') && !e.target.closest('.pdm-toolbar-filters-dropdown')) {
                    this.elements.filtersDropdown?.classList.remove('active');
                }
            });

            document.addEventListener('dragover', (e) => {
                if (this.state.draggedItem) {
                    e.preventDefault();
                    return;
                }

                if (!this.isExternalFileDrag(e)) {
                    return;
                }

                e.preventDefault();
                this.elements.uploadOverlay?.classList.add('active');
                this.elements.uploadOverlay?.querySelector('.pdm-upload-dropzone')?.classList.add('drag-over');
            });

            document.addEventListener('dragleave', (e) => {
                if (this.state.draggedItem || !this.isExternalFileDrag(e)) {
                    return;
                }

                if (e.relatedTarget === null || !document.body.contains(e.relatedTarget)) {
                    this.hideUploadOverlay();
                }
            });

            document.addEventListener('drop', (e) => {
                if (this.state.draggedItem) {
                    this.clearDropTargets();
                    this.state.draggedItem = null;
                    return;
                }

                if (!this.isExternalFileDrag(e)) {
                    return;
                }

                e.preventDefault();
                this.hideUploadOverlay();
                if (e.dataTransfer?.files?.length) {
                    this.handleFileSelect(e.dataTransfer.files);
                }
            });

            this.elements.folderTree?.addEventListener('click', (e) => {
                const toggle = e.target.closest('.pdm-folder-toggle');
                if (!toggle) return;
                e.stopPropagation();
                const folderItem = toggle.closest('.pdm-folder-item');
                toggle.classList.toggle('collapsed');
                const children = folderItem?.nextElementSibling;
                if (children) {
                    children.style.display = toggle.classList.contains('collapsed') ? 'none' : 'block';
                }
            });
        },

        async loadBrowser(folderId = null, page = 1, options = {}) {
            if (options.clearSearchInput && this.elements.searchInput) {
                this.elements.searchInput.value = '';
            }

            const seq = ++this.state._loadSeq;
            this.state.isLoading = true;
            this.state.isSearchMode = false;
            this.state.searchQuery = '';
            this.state.currentFolder = folderId;
            this.state.draggedItem = null;
            this.state.selectedFile = null;
            this.state.selectedFolder = null;
            this.clearDetails();
            this.renderContent();

            try {
                const params = new URLSearchParams({
                    folder_id: folderId || '',
                    order_by: this.state.sortBy,
                    order: this.state.sortOrder,
                    page: String(page),
                    per_page: String(this.state.pagination.perPage),
                });

                const response = await this.apiGet(`browser?${params}`);

                if (seq !== this.state._loadSeq) return;

                if (response.success) {
                    this.state.folders = response.data.folders;
                    this.state.files = response.data.files;
                    this.state.folderTree = response.data.folder_tree;
                    this.state.breadcrumb = response.data.breadcrumb;
                    this.state.storageStats = response.data.storage_stats;
                    this.state.permissions = response.data.permissions || null;
                    this.state.pagination = this.normalizePagination(response.data.pagination);
                    this.syncPerPageSelect();

                    this.renderFolderTree();
                    this.renderBreadcrumb();
                    this.renderStorageIndicator();
                    this.applyToolbarPermissions();
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                console.error('Load browser error:', error);
            } finally {
                if (seq === this.state._loadSeq) {
                    this.state.isLoading = false;
                    this.renderContent();
                }
            }
        },

        renderFolderTree() {
            const html = `
                <div class="pdm-folder-item pdm-folder-item--root ${this.state.currentFolder === null ? 'active' : ''}" data-folder-id="">
                    <span class="pdm-folder-spacer"></span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                    <span class="pdm-folder-name">${mstvConfig.i18n.rootFolder}</span>
                </div>
                ${this.buildFolderTreeHtml(this.state.folderTree)}
            `;
            this.elements.folderTree.innerHTML = html;

            this.elements.folderTree.querySelectorAll('.pdm-folder-item').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const folderId = el.dataset.folderId;
                    this.loadBrowser(folderId ? parseInt(folderId, 10) : null, 1, { clearSearchInput: true });
                });

                el.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    const folderId = el.dataset.folderId;
                    this.showFolderContextMenu(e, folderId ? parseInt(folderId) : null);
                });

                el.addEventListener('dragover', (e) => {
                    if (!this.state.draggedItem || this.state.draggedItem.type !== 'files') return;
                    e.preventDefault();
                    el.classList.add('pdm-drop-target');
                });

                el.addEventListener('dragleave', () => {
                    el.classList.remove('pdm-drop-target');
                });

                el.addEventListener('drop', (e) => {
                    if (!this.state.draggedItem || this.state.draggedItem.type !== 'files') return;

                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('pdm-drop-target');

                    const folderId = el.dataset.folderId ? parseInt(el.dataset.folderId, 10) : null;
                    this.handleInternalFileDrop(folderId);
                });
            });

        },

        buildFolderTreeHtml(folders, level = 0) {
            if (!folders || folders.length === 0) return '';

            return folders.map(folder => {
                const isActive = this.state.currentFolder === folder.id;
                const hasChildren = folder.has_children;
                const isDeep = level >= this.FOLDER_MAX_EXPANDED_DEPTH;
                const childrenCount = hasChildren ? this.countDescendants(folder) : 0;
                
                let html = `
                    <div class="pdm-folder-item ${isActive ? 'active' : ''} ${isDeep && hasChildren ? 'collapsed' : ''}" data-folder-id="${folder.id}" data-depth="${level}">
                        ${hasChildren ? `
                            <span class="pdm-folder-toggle" title="${isDeep ? mstvConfig.i18n.expand : mstvConfig.i18n.collapse}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        ` : '<span class="pdm-folder-spacer"></span>'}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="pdm-folder-name">${this.escapeHtml(folder.name)}</span>
                        ${this.folderRulesBadge(folder)}
                        ${isDeep && childrenCount > 0 ? `<span class="pdm-folder-count">+${childrenCount}</span>` : ''}
                    </div>
                `;

                if (hasChildren) {
                    html += `<div class="pdm-folder-children" style="display: ${isDeep ? 'none' : 'block'};">`;
                    html += this.buildFolderTreeHtml(folder.children, level + 1);
                    html += `</div>`;
                }

                return html;
            }).join('');
        },

        folderRulesBadge(folder) {
            if (!folder || !folder.has_rules) return '';
            const label = mstvConfig.i18n.folderRestricted;
            return `<span class="pdm-folder-badge" title="${this.escapeHtml(label)}" aria-label="${this.escapeHtml(label)}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>`;
        },

        countDescendants(folder) {
            if (!folder.children || folder.children.length === 0) return 0;
            let count = folder.children.length;
            folder.children.forEach(child => {
                count += this.countDescendants(child);
            });
            return count;
        },

        renderBreadcrumb() {
            const items = this.state.breadcrumb;
            const maxItems = window.innerWidth < 600 ? 2 : items.length;
            const showTruncated = items.length > maxItems + 1;

            let html = `
                <a href="#" class="pdm-breadcrumb-item ${this.state.currentFolder === null ? 'current' : ''}" data-folder-id="">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                    <span>${mstvConfig.i18n.rootFolder}</span>
                </a>
            `;

            if (showTruncated && items.length > 2) {
                html += `<span class="pdm-breadcrumb-separator">/</span>`;
                html += `<span class="pdm-breadcrumb-item truncated">...</span>`;
                const lastItem = items[items.length - 1];
                html += `<span class="pdm-breadcrumb-separator">/</span>`;
                html += `<a href="#" class="pdm-breadcrumb-item current" data-folder-id="${lastItem.id}">`;
                html += `<span>${this.escapeHtml(lastItem.name)}</span></a>`;
            } else {
                items.forEach((item, index) => {
                    const isLast = index === items.length - 1;
                    html += `
                        <span class="pdm-breadcrumb-separator">/</span>
                        <a href="#" class="pdm-breadcrumb-item ${isLast ? 'current' : ''}" data-folder-id="${item.id}">
                            <span>${this.escapeHtml(item.name)}</span>
                        </a>
                    `;
                });
            }

            this.elements.breadcrumb.innerHTML = html;

            this.elements.breadcrumb.querySelectorAll('.pdm-breadcrumb-item').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (el.classList.contains('truncated')) return;
                    const folderId = el.dataset.folderId;
                    this.loadBrowser(folderId ? parseInt(folderId, 10) : null, 1, { clearSearchInput: true });
                });

                el.addEventListener('dragover', (e) => {
                    if (!this.state.draggedItem || this.state.draggedItem.type !== 'files') return;
                    e.preventDefault();
                    el.classList.add('pdm-drop-target');
                });

                el.addEventListener('dragleave', () => {
                    el.classList.remove('pdm-drop-target');
                });

                el.addEventListener('drop', (e) => {
                    if (!this.state.draggedItem || this.state.draggedItem.type !== 'files') return;

                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('pdm-drop-target');

                    const folderId = el.dataset.folderId ? parseInt(el.dataset.folderId, 10) : null;
                    this.handleInternalFileDrop(folderId);
                });
            });
        },

        renderContent() {
            if (this.state.isLoading) {
                this.elements.content.innerHTML = `
                    <div class="pdm-loading">
                        <div class="pdm-spinner"></div>
                        <span>${mstvConfig.i18n.loading}</span>
                    </div>
                `;
                return;
            }

            const totalItems = this.state.folders.length + this.state.files.length;

            if (totalItems === 0) {
                if (this.state.isSearchMode) {
                    this.elements.content.innerHTML = `
                        <div class="pdm-empty-state">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <h3>${mstvConfig.i18n.noResults}</h3>
                            <p>${mstvConfig.i18n.searchNoResultsDesc}</p>
                        </div>
                    `;
                    return;
                }

                const actions = [];
                if (this.can('upload')) {
                    actions.push(`<button type="button" class="pdm-btn pdm-btn-primary pdm-empty-upload">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        ${mstvConfig.i18n.browseFiles}
                    </button>`);
                }
                if (this.can('manage')) {
                    actions.push(`<button type="button" class="pdm-btn pdm-btn-secondary pdm-empty-newfolder">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                        ${mstvConfig.i18n.untitledFolder}
                    </button>`);
                }

                this.elements.content.innerHTML = `
                    <div class="pdm-empty-state pdm-empty-state--dropzone">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                        <h3>${mstvConfig.i18n.emptyState}</h3>
                        <p>${this.can('upload') ? mstvConfig.i18n.dragDropHere : mstvConfig.i18n.emptyStateDesc}</p>
                        ${actions.length ? `<div class="pdm-empty-actions">${actions.join('')}</div>` : ''}
                    </div>
                `;
                this.elements.content.querySelector('.pdm-empty-upload')?.addEventListener('click', () => this.showUploadOverlay());
                this.elements.content.querySelector('.pdm-empty-newfolder')?.addEventListener('click', () => this.showNewFolderModal());
                return;
            }

            if (this.state.viewMode === 'grid') {
                this.renderGridView();
            } else {
                this.renderListView();
            }

            this.renderPagination();
        },

        renderGridView() {
            let html = '<div class="pdm-content-grid">';

            this.state.folders.forEach(folder => {
                html += `
                    <div class="pdm-item pdm-item--folder" data-type="folder" data-id="${folder.id}">
                        <div class="pdm-item-icon pdm-item-icon--folder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        <div class="pdm-item-name">${this.escapeHtml(folder.name)}${this.folderRulesBadge(folder)}</div>
                    </div>
                `;
            });

            this.state.files.forEach(files => {
                const iconContent = files.exists_on_disk && files.is_image
                    ? `<img src="${files.preview_url}" alt="${this.escapeHtml(files.display_name)}" loading="lazy">`
                    : this.getFileIconSvg(files.icon);
                const availabilityBadge = files.exists_on_disk
                    ? ''
                    : `<div class="pdm-item-status pdm-item-status--missing">${mstvConfig.i18n.fileMissingShort}</div>`;
                
                html += `
                    <div class="pdm-item pdm-item--files" data-type="files" data-id="${files.id}" draggable="true">
                        <div class="pdm-item-icon pdm-item-icon--${this.getFileIconClass(files.icon)}">
                            ${iconContent}
                        </div>
                        <div class="pdm-item-name">${this.escapeHtml(files.display_name)}</div>
                        ${availabilityBadge}
                        <div class="pdm-item-meta">${this.escapeHtml(files.file_size_formatted)}</div>
                    </div>
                `;
            });

            html += '</div>';
            this.elements.content.innerHTML = html;
            this.bindContentEvents();
        },

        renderListView() {
            let html = '<div class="pdm-content-list">';

            this.state.folders.forEach(folder => {
                html += `
                    <div class="pdm-list-item pdm-list-item--folder" data-type="folder" data-id="${folder.id}">
                        <div class="pdm-list-item-icon pdm-item-icon--folder">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        <div class="pdm-list-item-info">
                            <div class="pdm-list-item-name">${this.escapeHtml(folder.name)}${this.folderRulesBadge(folder)}</div>
                            <div class="pdm-list-item-meta">${mstvConfig.i18n.folder}</div>
                        </div>
                    </div>
                `;
            });

            this.state.files.forEach(files => {
                const iconContent = files.exists_on_disk && files.is_image
                    ? `<img src="${files.preview_url}" alt="${this.escapeHtml(files.display_name)}" loading="lazy">`
                    : this.getFileIconSvg(files.icon, 32);
                const statusText = files.exists_on_disk ? '' : ` · ${mstvConfig.i18n.fileMissingShort}`;
                
                html += `
                    <div class="pdm-list-item pdm-list-item--files" data-type="files" data-id="${files.id}" draggable="true">
                        <div class="pdm-list-item-icon pdm-item-icon--${this.getFileIconClass(files.icon)}">
                            ${iconContent}
                        </div>
                        <div class="pdm-list-item-info">
                            <div class="pdm-list-item-name">${this.escapeHtml(files.display_name)}</div>
                            <div class="pdm-list-item-meta">${this.escapeHtml(files.file_size_formatted)} · ${this.escapeHtml(files.created_at_human)}${statusText}</div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            this.elements.content.innerHTML = html;
            this.bindContentEvents();
        },

        renderPagination() {
            if (this.state.pagination.totalPages <= 1) {
                return;
            }

            const pager = document.createElement('div');
            const rangeLabel = this.state.isSearchMode ? mstvConfig.i18n.results : mstvConfig.i18n.files;

            pager.className = 'pdm-pagination';
            pager.innerHTML = `
                <div class="pdm-pagination-summary">
                    ${this.state.pagination.fromItem}-${this.state.pagination.toItem} ${mstvConfig.i18n.of} ${this.state.pagination.totalItems} ${rangeLabel}
                </div>
                <div class="pdm-pagination-controls">
                    <button type="button" class="pdm-btn pdm-btn-ghost pdm-pagination-btn" data-page="${this.state.pagination.page - 1}" ${this.state.pagination.hasPrev ? '' : 'disabled'}>
                        ${mstvConfig.i18n.previous}
                    </button>
                    <span class="pdm-pagination-status">
                        ${mstvConfig.i18n.page} ${this.state.pagination.page} ${mstvConfig.i18n.of} ${this.state.pagination.totalPages}
                    </span>
                    <button type="button" class="pdm-btn pdm-btn-ghost pdm-pagination-btn" data-page="${this.state.pagination.page + 1}" ${this.state.pagination.hasNext ? '' : 'disabled'}>
                        ${mstvConfig.i18n.next}
                    </button>
                </div>
            `;

            this.elements.content.appendChild(pager);
            this.bindPaginationEvents();
        },

        bindPaginationEvents() {
            this.elements.content.querySelectorAll('.pdm-pagination-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (btn.disabled) {
                        return;
                    }

                    const targetPage = parseInt(btn.dataset.page, 10);

                    if (Number.isNaN(targetPage) || targetPage < 1) {
                        return;
                    }

                    if (this.state.isSearchMode) {
                        this.search(this.state.searchQuery, targetPage);
                        return;
                    }

                    this.loadBrowser(this.state.currentFolder, targetPage);
                });
            });
        },

        bindContentEvents() {
            this.elements.content.querySelectorAll('.pdm-item, .pdm-list-item').forEach(el => {
                el.addEventListener('click', (e) => {
                    if (e.target.closest('.pdm-item-actions') || e.target.closest('.pdm-list-item-actions')) return;
                    
                    const type = el.dataset.type;
                    const id = parseInt(el.dataset.id);

                    if (type === 'folder') {
                        this.selectFolder(id);
                    } else {
                        this.selectFile(id);
                    }
                });

                if (el.dataset.type === 'folder') {
                    el.addEventListener('dblclick', () => {
                        const id = parseInt(el.dataset.id);
                        this.openFolder(id);
                    });
                }

                el.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    const type = el.dataset.type;
                    const id = parseInt(el.dataset.id);
                    
                    if (type === 'folder') {
                        this.showFolderContextMenu(e, id);
                    } else {
                        this.showFileContextMenu(e, id);
                    }
                });

                el.addEventListener('dragstart', (e) => {
                    if (el.dataset.type !== 'files') {
                        e.preventDefault();
                        return;
                    }

                    e.dataTransfer.setData('text/plain', JSON.stringify({
                        type: el.dataset.type,
                        id: parseInt(el.dataset.id)
                    }));
                    e.dataTransfer.effectAllowed = 'move';
                    this.state.draggedItem = {
                        type: el.dataset.type,
                        id: parseInt(el.dataset.id, 10)
                    };
                    el.classList.add('pdm-dragging');
                });

                el.addEventListener('dragend', () => {
                    el.classList.remove('pdm-dragging');
                    this.state.draggedItem = null;
                    this.clearDropTargets();
                });

                if (el.dataset.type === 'folder') {
                    el.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        el.classList.add('pdm-drop-target');
                    });

                    el.addEventListener('dragleave', () => {
                        el.classList.remove('pdm-drop-target');
                    });

                    el.addEventListener('drop', (e) => {
                        e.preventDefault();
                        el.classList.remove('pdm-drop-target');
                        
                        try {
                            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                            if (data.type === 'files') {
                                this.moveFile(data.id, parseInt(el.dataset.id, 10));
                            }
                        } catch (err) {
                            console.error('Drop error:', err);
                        } finally {
                            this.state.draggedItem = null;
                            this.clearDropTargets();
                        }
                    });
                }
            });

            this.elements.content.querySelectorAll('.pdm-action-rename').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = e.target.closest('[data-type]');
                    const type = el.dataset.type;
                    const id = parseInt(el.dataset.id);
                    if (type === 'folder') {
                        this.showRenameFolderModal(id);
                    } else {
                        this.showRenameFileModal(id);
                    }
                });
            });

            this.elements.content.querySelectorAll('.pdm-action-delete').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = e.target.closest('[data-type]');
                    const type = el.dataset.type;
                    const id = parseInt(el.dataset.id);
                    if (type === 'folder') {
                        this.deleteFolder(id);
                    } else {
                        this.deleteFile(id);
                    }
                });
            });

            this.elements.content.querySelectorAll('.pdm-action-download').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = e.target.closest('[data-type="files"]');
                    const id = parseInt(el.dataset.id);
                    this.downloadFile(id);
                });
            });

            this.elements.content.querySelectorAll('.pdm-action-preview').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = e.target.closest('[data-type="files"]');
                    const id = parseInt(el.dataset.id);
                    this.previewFile(id);
                });
            });

            this.elements.content.querySelectorAll('.pdm-action-move').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = e.target.closest('[data-type="files"]');
                    const id = parseInt(el.dataset.id);
                    this.showMoveFileModal(id);
                });
            });
        },

        selectFile(fileId) {
            const files = this.state.files.find(f => f.id === fileId);
            if (!files) return;

            this.state.selectedFile = files;
            this.state.selectedFolder = null;

            this.elements.content.querySelectorAll('.pdm-item, .pdm-list-item').forEach(el => {
                el.classList.remove('selected');
            });

            const selectedEl = this.elements.content.querySelector(`[data-type="files"][data-id="${fileId}"]`);
            if (selectedEl) {
                selectedEl.classList.add('selected');
            }

            this.renderFileDetails(files);
        },

        selectFolder(folderId) {
            const folder = this.state.folders.find(f => f.id === folderId);
            if (!folder) return;

            this.state.selectedFolder = folder;
            this.state.selectedFile = null;

            this.elements.content.querySelectorAll('.pdm-item, .pdm-list-item').forEach(el => {
                el.classList.remove('selected');
            });

            const selectedEl = this.elements.content.querySelector(`[data-type="folder"][data-id="${folderId}"]`);
            if (selectedEl) {
                selectedEl.classList.add('selected');
            }

            this.renderFolderDetails(folder);
        },

        openFolder(folderId) {
            this.state.selectedFolder = null;
            this.state.selectedFile = null;
            this.loadBrowser(folderId, 1, { clearSearchInput: true });
        },

        renderFileDetails(files) {
            const isAvailable = this.isFileAvailable(files);
            const html = `
                <div class="pdm-details-header">
                    <div class="pdm-details-header-top">
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-details-close" aria-label="${mstvConfig.i18n.close}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pdm-details-preview">
                        ${isAvailable && files.is_previewable && files.icon === 'image'
                            ? `<img src="${files.preview_url}" alt="${this.escapeHtml(files.display_name)}">`
                            : this.getFileIconSvg(files.icon, 64)
                        }
                    </div>
                    <div class="pdm-details-name">${this.escapeHtml(files.display_name)}</div>
                </div>
                <div class="pdm-details-body">
                    ${isAvailable ? '' : `
                        <div class="pdm-details-notice pdm-details-notice--warning">
                            <strong>${mstvConfig.i18n.fileMissing}</strong>
                            <span>${mstvConfig.i18n.fileMissingDesc}</span>
                        </div>
                    `}
                    <div class="pdm-details-section">
                        <div class="pdm-details-section-title">${mstvConfig.i18n.file}</div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.name}</span>
                            <span class="pdm-details-row-value">${this.escapeHtml(files.display_name)}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.type}</span>
                            <span class="pdm-details-row-value">.${files.extension.toUpperCase()}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.size}</span>
                            <span class="pdm-details-row-value">${this.escapeHtml(files.file_size_formatted)}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.created}</span>
                            <span class="pdm-details-row-value">${this.escapeHtml(files.created_at_human)}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.availability}</span>
                            <span class="pdm-details-row-value ${isAvailable ? '' : 'pdm-details-row-value--danger'}">${isAvailable ? mstvConfig.i18n.available : mstvConfig.i18n.missing}</span>
                        </div>
                    </div>
                </div>
                <div class="pdm-details-actions">
                    ${files.is_previewable ? `
                        <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-preview-btn" ${isAvailable ? '' : 'disabled'}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            ${mstvConfig.i18n.preview}
                        </button>
                    ` : ''}
                    ${this.can('download') ? `
                    <button type="button" class="pdm-btn pdm-btn-primary pdm-details-download-btn" ${isAvailable ? '' : 'disabled'}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        ${mstvConfig.i18n.download}
                    </button>
                    ` : ''}
                    ${this.can('manage') ? `
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-rename-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        ${mstvConfig.i18n.rename}
                    </button>
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-move-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="5 9 2 12 5 15"/>
                            <polyline points="9 5 12 2 15 5"/>
                            <polyline points="15 19 12 22 9 19"/>
                            <polyline points="19 9 22 12 19 15"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <line x1="12" y1="2" x2="12" y2="22"/>
                        </svg>
                        ${mstvConfig.i18n.move}
                    </button>
                    ` : ''}
                    ${this.can('delete') ? `
                    <button type="button" class="pdm-btn pdm-btn-danger pdm-details-delete-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        ${mstvConfig.i18n.delete}
                    </button>
                    ` : ''}
                </div>
            `;

            this.elements.details.innerHTML = html;

            this.elements.details.querySelector('.pdm-details-preview-btn')?.addEventListener('click', () => {
                this.previewFile(files.id);
            });

            this.elements.details.querySelector('.pdm-details-download-btn')?.addEventListener('click', () => {
                this.downloadFile(files.id);
            });

            this.elements.details.querySelector('.pdm-details-rename-btn')?.addEventListener('click', () => {
                this.showRenameFileModal(files.id);
            });

            this.elements.details.querySelector('.pdm-details-move-btn')?.addEventListener('click', () => {
                this.showMoveFileModal(files.id);
            });

            this.elements.details.querySelector('.pdm-details-delete-btn')?.addEventListener('click', () => {
                this.deleteFile(files.id);
            });

            this.elements.details.querySelector('.pdm-details-close')?.addEventListener('click', () => {
                this.dismissDetails();
            });

            this.openMobileDetails();
        },

        renderFolderDetails(folder) {
            const html = `
                <div class="pdm-details-header">
                    <div class="pdm-details-header-top">
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-details-close" aria-label="${mstvConfig.i18n.close}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pdm-details-preview">
                        ${this.getFileIconSvg('folder', 64)}
                    </div>
                    <div class="pdm-details-name">${this.escapeHtml(folder.name)}</div>
                </div>
                <div class="pdm-details-body">
                    <div class="pdm-details-section">
                        <div class="pdm-details-section-title">${mstvConfig.i18n.folder}</div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.name}</span>
                            <span class="pdm-details-row-value">${this.escapeHtml(folder.name)}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.type}</span>
                            <span class="pdm-details-row-value">${mstvConfig.i18n.folder}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.created}</span>
                            <span class="pdm-details-row-value">${this.escapeHtml(folder.created_at_human)}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.status}</span>
                            <span class="pdm-details-row-value">${folder.has_children ? mstvConfig.i18n.folderHasChildren : mstvConfig.i18n.folderEmpty}</span>
                        </div>
                    </div>
                </div>
                <div class="pdm-details-actions">
                    <button type="button" class="pdm-btn pdm-btn-primary pdm-details-open-folder-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        ${mstvConfig.i18n.open}
                    </button>
                    ${this.folderCan(folder, 'manage') ? `
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-permissions-folder-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        ${mstvConfig.i18n.permissions}
                    </button>
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-rename-folder-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        ${mstvConfig.i18n.rename}
                    </button>
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-details-move-folder-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="5 9 2 12 5 15"/>
                            <polyline points="9 5 12 2 15 5"/>
                            <polyline points="15 19 12 22 9 19"/>
                            <polyline points="19 9 22 12 19 15"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <line x1="12" y1="2" x2="12" y2="22"/>
                        </svg>
                        ${mstvConfig.i18n.move}
                    </button>
                    ` : ''}
                    ${this.folderCan(folder, 'delete') ? `
                    <button type="button" class="pdm-btn pdm-btn-danger pdm-details-delete-folder-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        ${mstvConfig.i18n.delete}
                    </button>
                    ` : ''}
                </div>
            `;

            this.elements.details.innerHTML = html;

            this.elements.details.querySelector('.pdm-details-open-folder-btn')?.addEventListener('click', () => {
                this.openFolder(folder.id);
            });

            this.elements.details.querySelector('.pdm-details-permissions-folder-btn')?.addEventListener('click', () => {
                this.openFolderPermissions(folder.id, folder.name);
            });

            this.elements.details.querySelector('.pdm-details-rename-folder-btn')?.addEventListener('click', () => {
                this.showRenameFolderModal(folder.id);
            });

            this.elements.details.querySelector('.pdm-details-move-folder-btn')?.addEventListener('click', () => {
                this.showMoveFolderModal(folder.id);
            });

            this.elements.details.querySelector('.pdm-details-delete-folder-btn')?.addEventListener('click', () => {
                this.deleteFolder(folder.id);
            });

            this.elements.details.querySelector('.pdm-details-close')?.addEventListener('click', () => {
                this.dismissDetails();
            });

            this.openMobileDetails();
        },

        showNewFolderModal() {
            const html = `
                <div class="pdm-field">
                    <label class="pdm-field-label">${mstvConfig.i18n.name}</label>
                    <input type="text" class="pdm-input" id="pdm-folder-name" value="${mstvConfig.i18n.untitledFolder}" autofocus>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-modal-confirm">${mstvConfig.i18n.confirm}</button>
            `;

            this.showModal(mstvConfig.i18n.newFolder, html, footer);

            const nameInput = document.getElementById('pdm-folder-name');
            nameInput?.select();

            this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-confirm')?.addEventListener('click', async () => {
                const name = nameInput?.value?.trim();
                if (name) {
                    await this.createFolder(name);
                }
            });
        },

        showRenameFolderModal(folderId) {
            const folder = this.state.folders.find(f => f.id === folderId);
            if (!folder) return;

            const html = `
                <div class="pdm-field">
                    <label class="pdm-field-label">${mstvConfig.i18n.name}</label>
                    <input type="text" class="pdm-input" id="pdm-folder-name" value="${this.escapeHtml(folder.name)}" autofocus>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-modal-confirm">${mstvConfig.i18n.confirm}</button>
            `;

            this.showModal(mstvConfig.i18n.rename, html, footer);

            const nameInput = document.getElementById('pdm-folder-name');
            nameInput?.select();

            this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-confirm')?.addEventListener('click', async () => {
                const name = nameInput?.value?.trim();
                if (name) {
                    await this.renameFolder(folderId, name);
                }
            });
        },

        showRenameFileModal(fileId) {
            const files = this.state.files.find(f => f.id === fileId);
            if (!files) return;

            const fallbackName = files.original_name
                ? files.original_name.replace(/\.[^/.]+$/, '')
                : '';
            const initialName = (files.display_name || fallbackName || '').trim();

            const html = `
                <div class="pdm-field">
                    <label class="pdm-field-label">${mstvConfig.i18n.name}</label>
                    <input type="text" class="pdm-input" id="pdm-files-name" value="${this.escapeHtml(initialName)}" autofocus>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-modal-confirm">${mstvConfig.i18n.confirm}</button>
            `;

            this.showModal(mstvConfig.i18n.rename, html, footer);

            const nameInput = document.getElementById('pdm-files-name');
            nameInput?.select();

            this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-confirm')?.addEventListener('click', async () => {
                const name = nameInput?.value?.trim();
                if (name) {
                    await this.renameFile(fileId, name);
                }
            });
        },

        showMoveFileModal(fileId) {
            const files = this.state.files.find(f => f.id === fileId);
            if (!files) return;

            const folderTreeHtml = this.buildMoveTreeHtml(this.state.folderTree, files.folder_id);

            const html = `
                <div class="pdm-field">
                    <label class="pdm-field-label">${mstvConfig.i18n.moveTo}</label>
                    <div class="pdm-move-tree">
                        <div class="pdm-move-item ${files.folder_id === null ? 'current' : ''}" data-folder-id="">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            </svg>
                            <span>${mstvConfig.i18n.rootFolder}</span>
                        </div>
                        ${folderTreeHtml}
                    </div>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-modal-confirm" disabled>${mstvConfig.i18n.confirm}</button>
            `;

            this.showModal(mstvConfig.i18n.move, html, footer);

            let selectedFolderId = null;

            this.elements.modal.querySelectorAll('.pdm-move-item').forEach(el => {
                el.addEventListener('click', () => {
                    if (el.classList.contains('current')) return;
                    
                    this.elements.modal.querySelectorAll('.pdm-move-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    el.classList.add('active');
                    
                    selectedFolderId = el.dataset.folderId ? parseInt(el.dataset.folderId) : null;
                    this.elements.modal.querySelector('.pdm-modal-confirm')?.removeAttribute('disabled');
                });
            });

            this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-confirm')?.addEventListener('click', async () => {
                await this.moveFile(fileId, selectedFolderId);
            });
        },

        showMoveFolderModal(folderId) {
            const folder = this.state.folders.find(f => f.id === folderId);
            if (!folder) return;

            const currentParentId = folder.parent_id ?? null;
            // Exclude the folder itself and its subtree from the destinations to prevent cycles.
            const folderTreeHtml = this.buildMoveTreeHtml(this.state.folderTree, currentParentId, 0, folderId);

            const html = `
                <div class="pdm-field">
                    <label class="pdm-field-label">${mstvConfig.i18n.moveTo}</label>
                    <div class="pdm-move-tree">
                        <div class="pdm-move-item ${currentParentId === null ? 'current' : ''}" data-folder-id="">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            </svg>
                            <span>${mstvConfig.i18n.rootFolder}</span>
                        </div>
                        ${folderTreeHtml}
                    </div>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-modal-confirm" disabled>${mstvConfig.i18n.confirm}</button>
            `;

            this.showModal(mstvConfig.i18n.move, html, footer);

            let selectedParentId = null;

            this.elements.modal.querySelectorAll('.pdm-move-item').forEach(el => {
                el.addEventListener('click', () => {
                    if (el.classList.contains('current')) return;

                    this.elements.modal.querySelectorAll('.pdm-move-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    el.classList.add('active');

                    selectedParentId = el.dataset.folderId ? parseInt(el.dataset.folderId) : null;
                    this.elements.modal.querySelector('.pdm-modal-confirm')?.removeAttribute('disabled');
                });
            });

            this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-confirm')?.addEventListener('click', async () => {
                await this.moveFolder(folderId, selectedParentId);
            });
        },

        buildMoveTreeHtml(folders, currentFolderId, level = 0, excludeFolderId = null) {
            if (!folders || folders.length === 0) return '';

            return folders.map(folder => {
                if (excludeFolderId !== null && folder.id === excludeFolderId) {
                    return '';
                }

                const isCurrent = folder.id === currentFolderId;
                let html = `
                    <div class="pdm-move-item ${isCurrent ? 'current' : ''}" data-folder-id="${folder.id}" style="padding-left: ${12 + level * 20}px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>${this.escapeHtml(folder.name)}</span>
                    </div>
                `;

                if (folder.children && folder.children.length > 0) {
                    html += this.buildMoveTreeHtml(folder.children, currentFolderId, level + 1, excludeFolderId);
                }

                return html;
            }).join('');
        },

        async createFolder(name) {
            try {
                const payload = { name };

                if (this.state.currentFolder !== null) {
                    payload.parent_id = this.state.currentFolder;
                }

                const response = await this.apiPost('folders', payload);

                if (response.success) {
                    this.showToast(mstvConfig.i18n.folderCreateSuccess, 'success');
                    this.hideModal();
                    await this.loadBrowser(this.state.currentFolder, 1, { clearSearchInput: true });
                } else {
                    this.showToast(response.message || mstvConfig.i18n.folderCreateError, 'error');
                }
            } catch (error) {
                this.showToast(error.message || mstvConfig.i18n.folderCreateError, 'error');
                console.error('Create folder error:', error);
            }
        },

        async renameFolder(folderId, name) {
            try {
                const response = await this.apiPatch(`folders/${folderId}`, { name });

                if (response.success) {
                    this.showToast(mstvConfig.i18n.renameSuccess, 'success');
                    this.hideModal();
                    await this.loadBrowser(this.state.currentFolder, 1, { clearSearchInput: true });
                } else {
                    this.showToast(response.message || mstvConfig.i18n.renameError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.renameError, 'error');
                console.error('Rename folder error:', error);
            }
        },

        async deleteFolder(folderId) {
            const confirmed = await this.confirmDialog(mstvConfig.i18n.confirmDeleteFolder, {
                danger: true,
                confirmLabel: mstvConfig.i18n.delete,
            });
            if (!confirmed) return;

            try {
                const response = await this.apiDelete(`folders/${folderId}`);

                if (response.success) {
                    this.showToast(mstvConfig.i18n.deleteSuccess, 'success');
                    await this.loadBrowser(this.state.currentFolder, 1, { clearSearchInput: true });
                } else {
                    this.showToast(response.message || mstvConfig.i18n.deleteError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.deleteError, 'error');
                console.error('Delete folder error:', error);
            }
        },

        async renameFile(fileId, name) {
            try {
                const response = await this.apiPatch(`files/${fileId}`, { display_name: name });

                if (response.success) {
                    this.showToast(mstvConfig.i18n.renameSuccess, 'success');
                    this.hideModal();
                    await this.reloadCurrentView();
                } else {
                    this.showToast(response.message || mstvConfig.i18n.renameError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.renameError, 'error');
                console.error('Rename files error:', error);
            }
        },

        async deleteFile(fileId) {
            const confirmed = await this.confirmDialog(mstvConfig.i18n.confirmDelete, {
                danger: true,
                confirmLabel: mstvConfig.i18n.delete,
            });
            if (!confirmed) return;

            try {
                const response = await this.apiDelete(`files/${fileId}`);

                if (response.success) {
                    this.showToast(mstvConfig.i18n.deleteSuccess, 'success');
                    await this.reloadCurrentView();
                    this.clearDetails();
                } else {
                    this.showToast(response.message || mstvConfig.i18n.deleteError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.deleteError, 'error');
                console.error('Delete files error:', error);
            }
        },

        async moveFile(fileId, targetFolderId) {
            try {
                const payload = {};

                if (targetFolderId !== null) {
                    payload.folder_id = targetFolderId;
                }

                const response = await this.apiPost(`files/${fileId}/move`, payload);

                if (response.success) {
                    this.showToast(mstvConfig.i18n.moveSuccess, 'success');
                    this.hideModal();
                    await this.reloadCurrentView();
                    this.clearDetails();
                } else {
                    this.showToast(response.message || mstvConfig.i18n.moveError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.moveError, 'error');
                console.error('Move files error:', error);
            }
        },

        async moveFolder(folderId, targetParentId) {
            try {
                const payload = {};

                if (targetParentId !== null) {
                    payload.parent_id = targetParentId;
                }

                const response = await this.apiPost(`folders/${folderId}/move`, payload);

                if (response.success) {
                    this.showToast(mstvConfig.i18n.folderMoveSuccess, 'success');
                    this.hideModal();
                    await this.loadBrowser(this.state.currentFolder, 1, { clearSearchInput: true });
                    this.clearDetails();
                } else {
                    this.showToast(response.message || mstvConfig.i18n.folderMoveError, 'error');
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.folderMoveError, 'error');
                console.error('Move folder error:', error);
            }
        },

        async handleInternalFileDrop(targetFolderId) {
            if (!this.state.draggedItem || this.state.draggedItem.type !== 'files') {
                return;
            }

            const fileId = this.state.draggedItem.id;
            this.state.draggedItem = null;
            this.clearDropTargets();

            await this.moveFile(fileId, targetFolderId);
        },

        downloadFile(fileId) {
            const files = this.state.files.find(f => f.id === fileId);
            if (!files) return;

            if (!this.isFileAvailable(files) || !files.download_url) {
                this.showToast(mstvConfig.i18n.fileMissingDesc, 'warning');
                return;
            }

            const link = document.createElement('a');
            link.href = files.download_url;
            link.download = files.display_name;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        previewFile(fileId) {
            const files = this.state.files.find(f => f.id === fileId);
            if (!files || !files.is_previewable) return;

            if (!this.isFileAvailable(files) || !files.preview_url) {
                this.showToast(mstvConfig.i18n.fileMissingDesc, 'warning');
                return;
            }

            this.elements.previewContainer.innerHTML = '';

            if (files.mime_type === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = files.preview_url;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                this.elements.previewContainer.appendChild(iframe);
            } else {
                const img = document.createElement('img');
                img.src = files.preview_url;
                img.alt = files.display_name;
                this.elements.previewContainer.appendChild(img);
            }

            this.elements.previewDownload.onclick = () => this.downloadFile(fileId);
            this.elements.previewModal.classList.add('active');
        },

        hidePreview() {
            this.elements.previewModal.classList.remove('active');
            this.elements.previewContainer.innerHTML = '';
        },

        async search(query, page = 1) {
            const normalizedQuery = String(query || '').trim();

            if (normalizedQuery.length < 2) {
                this.loadBrowser(this.state.currentFolder, 1);
                return;
            }

            const seq = ++this.state._loadSeq;
            this.state.isLoading = true;
            this.state.isSearchMode = true;
            this.state.searchQuery = normalizedQuery;
            this.state.draggedItem = null;
            this.state.selectedFile = null;
            this.state.selectedFolder = null;
            this.clearDetails();
            this.renderContent();

            try {
                const params = new URLSearchParams({
                    q: normalizedQuery,
                    order_by: this.state.sortBy,
                    order: this.state.sortOrder,
                    page: String(page),
                    per_page: String(this.state.pagination.perPage),
                });

                const response = await this.apiGet(`search?${params}`);

                if (seq !== this.state._loadSeq) return;

                if (response.success) {
                    this.state.folders = response.data.folders;
                    this.state.files = response.data.files;
                    this.state.pagination = this.normalizePagination(response.data.pagination);
                    this.syncPerPageSelect();
                }
            } catch (error) {
                this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                console.error('Search error:', error);
            } finally {
                if (seq === this.state._loadSeq) {
                    this.state.isLoading = false;
                    this.renderContent();
                }
            }
        },

        showUploadOverlay() {
            this.elements.uploadOverlay?.classList.add('active');
        },

        hideUploadOverlay() {
            this.elements.uploadOverlay?.classList.remove('active');
            this.elements.uploadOverlay?.querySelector('.pdm-upload-dropzone')?.classList.remove('drag-over');
        },

        showUploadProgress(controller) {
            let el = document.getElementById('pdm-upload-progress');
            if (!el) {
                el = document.createElement('div');
                el.id = 'pdm-upload-progress';
                el.className = 'pdm-upload-progress';
                document.body.appendChild(el);
            }

            el.innerHTML = `
                <span class="pdm-spinner pdm-upload-progress-spinner"></span>
                <span class="pdm-upload-progress-text" id="pdm-upload-progress-text">${this.escapeHtml(mstvConfig.i18n.uploading)}</span>
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-upload-progress-cancel">${mstvConfig.i18n.cancel}</button>
            `;

            el.querySelector('.pdm-upload-progress-cancel')?.addEventListener('click', () => {
                controller.abort();
                this.showToast(mstvConfig.i18n.uploadCancelled, 'info');
            });
        },

        updateUploadProgress(index, total, fileName) {
            const el = document.getElementById('pdm-upload-progress-text');
            if (!el) return;

            const label = total > 1
                ? `${mstvConfig.i18n.uploading} (${index}/${total})`
                : mstvConfig.i18n.uploading;
            el.textContent = `${label} — ${fileName}`;
        },

        hideUploadProgress() {
            document.getElementById('pdm-upload-progress')?.remove();
        },

        async handleFileSelect(files) {
            if (!files || files.length === 0) return;

            this.hideUploadOverlay();

            const fileList = Array.from(files);
            const controller = new AbortController();
            this.showUploadProgress(controller);

            let anySuccess = false;
            try {
                let index = 0;
                for (const file of fileList) {
                    if (controller.signal.aborted) break;
                    index += 1;
                    this.updateUploadProgress(index, fileList.length, file.name);
                    if (await this.uploadFile(file, controller.signal)) {
                        anySuccess = true;
                    }
                }
            } finally {
                this.hideUploadProgress();
            }

            if (anySuccess) {
                await this.reloadCurrentView();
            }
        },

        async uploadFile(file, signal = null) {
            if (mstvConfig.maxFileSize > 0 && file.size > mstvConfig.maxFileSize) {
                const msg = mstvConfig.i18n.fileTooLarge
                    .replace('{fileName}', file.name)
                    .replace('{fileSize}', this.formatBytes(file.size))
                    .replace('{maxSize}', this.formatBytes(mstvConfig.maxFileSize));
                this.showToast(msg, 'error');
                return false;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('display_name', file.name.replace(/\.[^/.]+$/, ''));

            if (this.state.currentFolder) {
                formData.append('folder_id', this.state.currentFolder);
            }

            try {
                const response = await fetch(this.buildApiUrl('files/upload'), {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': mstvConfig.restNonce,
                    },
                    body: formData,
                    signal,
                });

                const data = await this.parseApiResponse(response);

                if (data.success) {
                    this.showToast(mstvConfig.i18n.uploadSuccess, 'success');
                    return true;
                }
                this.showToast(data.message || mstvConfig.i18n.uploadError, 'error');
                return false;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return false;
                }
                this.showToast(error.message || mstvConfig.i18n.uploadError, 'error');
                console.error('Upload error:', error);
                return false;
            }
        },

        updateSort(sortBy) {
            this.state.sortBy = sortBy;

            if (this.state.isSearchMode) {
                this.search(this.state.searchQuery, 1);
                return;
            }

            this.loadBrowser(this.state.currentFolder, 1);
        },

        updatePerPage(value) {
            const nextPerPage = parseInt(value, 10);

            if (!this.isValidPerPage(nextPerPage)) {
                this.syncPerPageSelect();
                return;
            }

            this.state.pagination.perPage = nextPerPage;
            this.setStoredBrowserPerPage(nextPerPage);
            this.syncPerPageSelect();

            if (this.state.isSearchMode) {
                this.search(this.state.searchQuery, 1);
                return;
            }

            this.loadBrowser(this.state.currentFolder, 1);
        },

        toggleSortOrder() {
            this.state.sortOrder = this.state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
            this.syncSortOrderButton();

            if (this.state.isSearchMode) {
                this.search(this.state.searchQuery, 1);
                return;
            }

            this.loadBrowser(this.state.currentFolder, 1);
        },

        setViewMode(mode) {
            this.state.viewMode = mode;
            
            if (mode === 'grid') {
                this.elements.viewGridBtn?.classList.add('active');
                this.elements.viewListBtn?.classList.remove('active');
            } else {
                this.elements.viewListBtn?.classList.add('active');
                this.elements.viewGridBtn?.classList.remove('active');
            }

            this.renderContent();
        },

        toggleSidebar() {
            if (!this.isSidebarDrawerViewport()) {
                return;
            }

            this.state.sidebarOpen = !this.state.sidebarOpen;
            this.elements.sidebar?.classList.toggle('open', this.state.sidebarOpen);
            
            if (this.state.sidebarOpen) {
                this.showBackdrop();
                document.body.classList.add('pdm-no-scroll');
            } else {
                this.hideBackdrop();
                document.body.classList.remove('pdm-no-scroll');
            }
        },

        closeSidebar() {
            if (this.state.sidebarOpen) {
                this.state.sidebarOpen = false;
                this.elements.sidebar?.classList.remove('open');
                this.hideBackdrop();
                document.body.classList.remove('pdm-no-scroll');
            }
        },

        closeDetails() {
            if (this.state.detailsOpen) {
                this.state.detailsOpen = false;
                this.elements.details?.classList.remove('open');
                this.hideBackdrop();
                document.body.classList.remove('pdm-no-scroll');
            }
        },

        dismissDetails() {
            if (this.isDetailsDrawerViewport()) {
                this.closeDetails();
            } else {
                this.clearDetails();
            }
        },

        closeMobilePanels() {
            this.closeSidebar();
            this.closeDetails();
        },

        showBackdrop() {
            this.elements.backdrop?.classList.add('active');
        },

        hideBackdrop() {
            this.elements.backdrop?.classList.remove('active');
        },

        openMobileDetails() {
            if (this.isDetailsDrawerViewport()) {
                this.state.detailsOpen = true;
                this.elements.details?.classList.add('open');
                this.showBackdrop();
                document.body.classList.add('pdm-no-scroll');
            }
        },

        toggleFiltersDropdown() {
            const dropdown = this.elements.filtersDropdown;
            if (!dropdown) return;

            const isActive = dropdown.classList.contains('active');
            
            document.querySelectorAll('.pdm-toolbar-filters-dropdown.active').forEach(d => {
                d.classList.remove('active');
            });

            if (!isActive) {
                dropdown.classList.add('active');
                
                if (this.elements.sortSelect && this.elements.filtersSort) {
                    this.elements.filtersSort.value = this.state.sortBy;
                }
                if (this.elements.perPageSelect && this.elements.filtersPerPage) {
                    this.elements.filtersPerPage.value = String(this.state.pagination.perPage);
                }
            }
        },

        clearDetails() {
            this.state.selectedFile = null;
            this.state.selectedFolder = null;
            this.elements.content?.querySelectorAll('.pdm-item, .pdm-list-item').forEach(el => {
                el.classList.remove('selected');
            });
            this.closeDetails();
            this.elements.details.innerHTML = `
                <div class="pdm-details-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <p>${mstvConfig.i18n.selectItem}</p>
                </div>
            `;
        },

        clearDropTargets() {
            document.querySelectorAll('.pdm-drop-target').forEach((el) => {
                el.classList.remove('pdm-drop-target');
            });
        },

        showModal(title, body, footer) {
            this.elements.modalTitle.textContent = title;
            this.elements.modalBody.innerHTML = body;
            this.elements.modalFooter.innerHTML = footer;
            this.elements.modal.classList.add('active');

            const firstInput = this.elements.modalBody.querySelector('input, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        },

        hideModal() {
            this.elements.modal.classList.remove('active');
        },

        confirmDialog(message, options = {}) {
            return new Promise((resolve) => {
                const danger = options.danger === true;
                const confirmLabel = options.confirmLabel || mstvConfig.i18n.confirm;
                const body = `<p class="pdm-confirm-message">${this.escapeHtml(message)}</p>`;
                const footer = `
                    <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                    <button type="button" class="pdm-btn ${danger ? 'pdm-btn-danger' : 'pdm-btn-primary'} pdm-confirm-accept">${confirmLabel}</button>
                `;

                this.showModal(options.title || mstvConfig.i18n.confirm, body, footer);

                let settled = false;
                const finish = (result) => {
                    if (settled) return;
                    settled = true;
                    observer.disconnect();
                    this.hideModal();
                    resolve(result);
                };

                // Any dismissal (backdrop, close button, Escape) removes the "active"
                // class through the shared handlers; treat all of them as a cancel.
                const observer = new MutationObserver(() => {
                    if (!this.elements.modal.classList.contains('active')) {
                        finish(false);
                    }
                });
                observer.observe(this.elements.modal, { attributes: true, attributeFilter: ['class'] });

                this.elements.modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => finish(false));
                this.elements.modal.querySelector('.pdm-confirm-accept')?.addEventListener('click', () => finish(true));
            });
        },

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = 'pdm-toast';
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

            const iconSvg = type === 'success' 
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : type === 'error'
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

            toast.innerHTML = `
                <span class="pdm-toast-icon pdm-toast-icon--${type}">${iconSvg}</span>
                <span class="pdm-toast-message">${this.escapeHtml(message)}</span>
                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-toast-close" aria-label="${this.escapeHtml(mstvConfig.i18n.close)}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `;

            this.elements.toastContainer.appendChild(toast);

            toast.querySelector('.pdm-toast-close')?.addEventListener('click', () => {
                toast.remove();
            });

            setTimeout(() => {
                toast.style.animation = 'pdm-slide-in 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        },

        showFolderContextMenu(e, folderId) {
            this.hideContextMenu();

            const menu = document.createElement('div');
            menu.className = 'context-menu';
            menu.innerHTML = `
                <div class="context-menu-item pdm-context-open">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    ${mstvConfig.i18n.folder}
                </div>
                <div class="context-menu-item pdm-context-rename">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    ${mstvConfig.i18n.rename}
                </div>
                <div class="context-menu-item pdm-context-move">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="5 9 2 12 5 15"/>
                        <polyline points="9 5 12 2 15 5"/>
                        <polyline points="15 19 12 22 9 19"/>
                        <polyline points="19 9 22 12 19 15"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <line x1="12" y1="2" x2="12" y2="22"/>
                    </svg>
                    ${mstvConfig.i18n.move}
                </div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item context-menu-item--danger pdm-context-delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    ${mstvConfig.i18n.delete}
                </div>
            `;

            menu.style.left = `${e.pageX}px`;
            menu.style.top = `${e.pageY}px`;
            document.body.appendChild(menu);

            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                menu.style.left = `${window.innerWidth - rect.width - 10}px`;
            }
            if (rect.bottom > window.innerHeight) {
                menu.style.top = `${window.innerHeight - rect.height - 10}px`;
            }

            menu.querySelector('.pdm-context-open')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.loadBrowser(folderId, 1, { clearSearchInput: true });
            });

            menu.querySelector('.pdm-context-rename')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.showRenameFolderModal(folderId);
            });

            menu.querySelector('.pdm-context-move')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.showMoveFolderModal(folderId);
            });

            menu.querySelector('.pdm-context-delete')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.deleteFolder(folderId);
            });

            document.addEventListener('click', this._boundHideContextMenu);
        },

        showFileContextMenu(e, fileId) {
            this.hideContextMenu();

            const files = this.state.files.find(f => f.id === fileId);
            if (!files) return;
            const isAvailable = this.isFileAvailable(files);

            const menu = document.createElement('div');
            menu.className = 'context-menu';
            menu.innerHTML = `
                ${files.is_previewable ? `
                    <div class="context-menu-item pdm-context-preview">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        ${mstvConfig.i18n.preview}
                    </div>
                ` : ''}
                <div class="context-menu-item pdm-context-download ${isAvailable ? '' : 'context-menu-item--disabled'}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    ${mstvConfig.i18n.download}
                </div>
                <div class="context-menu-item pdm-context-rename">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    ${mstvConfig.i18n.rename}
                </div>
                <div class="context-menu-item pdm-context-move">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="5 9 2 12 5 15"/>
                        <polyline points="9 5 12 2 15 5"/>
                        <polyline points="15 19 12 22 9 19"/>
                        <polyline points="19 9 22 12 19 15"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <line x1="12" y1="2" x2="12" y2="22"/>
                    </svg>
                    ${mstvConfig.i18n.move}
                </div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item context-menu-item--danger pdm-context-delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    ${mstvConfig.i18n.delete}
                </div>
            `;

            menu.style.left = `${e.pageX}px`;
            menu.style.top = `${e.pageY}px`;
            document.body.appendChild(menu);

            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                menu.style.left = `${window.innerWidth - rect.width - 10}px`;
            }
            if (rect.bottom > window.innerHeight) {
                menu.style.top = `${window.innerHeight - rect.height - 10}px`;
            }

            menu.querySelector('.pdm-context-preview')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.previewFile(fileId);
            });

            menu.querySelector('.pdm-context-download')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.downloadFile(fileId);
            });

            menu.querySelector('.pdm-context-rename')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.showRenameFileModal(fileId);
            });

            menu.querySelector('.pdm-context-move')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.showMoveFileModal(fileId);
            });

            menu.querySelector('.pdm-context-delete')?.addEventListener('click', () => {
                this.hideContextMenu();
                this.deleteFile(fileId);
            });

            document.addEventListener('click', this._boundHideContextMenu);
        },

        hideContextMenu() {
            document.querySelectorAll('.context-menu').forEach(menu => menu.remove());
            document.removeEventListener('click', this._boundHideContextMenu);
        },

        renderStorageIndicator() {
            const stats = this.state.storageStats;
            if (!stats) return;

            const teamVaultEl = document.getElementById('pdm-storage-teamvault');
            const summaryEl = document.getElementById('pdm-storage-summary');

            if (teamVaultEl) teamVaultEl.textContent = stats.plugin_used_formatted || '--';
            if (summaryEl) {
                summaryEl.textContent = `${mstvConfig.i18n.usedByTeamVault}: ${stats.plugin_used_formatted || '--'}.`;
            }
        },

        isFileAvailable(files) {
            return Boolean(files && files.exists_on_disk);
        },

        getFileIconClass(icon) {
            const classes = {
                'pdf': 'pdf',
                'word': 'document',
                'excel': 'spreadsheet',
                'image': 'image',
                'archive': 'archive',
            };
            return classes[icon] || 'document';
        },

        getFileIconSvg(icon, size = 48) {
            const svgs = {
                'folder': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>`,
                'pdf': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M9 15v-2h2a1 1 0 0 1 0 2H9z"/>
                    <path d="M9 15v2"/>
                    <path d="M13 13v4"/>
                    <path d="M13 15h2"/>
                </svg>`,
                'word': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M8 13l2 4 2-4"/>
                    <path d="M16 13l-2 4"/>
                </svg>`,
                'excel': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M8 13h8"/>
                    <path d="M8 17h8"/>
                    <path d="M12 13v4"/>
                </svg>`,
                'image': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>`,
                'archive': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 8v13H3V8"/>
                    <path d="M1 3h22v5H1z"/>
                    <path d="M10 12h4"/>
                </svg>`,
                'default': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>`,
            };

            return svgs[icon] || svgs['default'];
        },

        syncPerPageSelect() {
            if (!this.elements.perPageSelect) {
                return;
            }

            this.elements.perPageSelect.value = String(this.state.pagination.perPage);
        },

        syncSortOrderButton() {
            if (!this.elements.sortOrderBtn) {
                return;
            }

            const isDescending = this.state.sortOrder === 'DESC';

            this.elements.sortOrderBtn.classList.toggle('active', isDescending);
            this.elements.sortOrderBtn.setAttribute('aria-pressed', String(isDescending));
        },

        normalizePagination(pagination = {}) {
            return {
                page: Number(pagination.page) || 1,
                perPage: Number(pagination.per_page) || this.state.pagination.perPage,
                totalItems: Number(pagination.total_items) || 0,
                totalPages: Number(pagination.total_pages) || 0,
                hasPrev: Boolean(pagination.has_prev),
                hasNext: Boolean(pagination.has_next),
                fromItem: Number(pagination.from_item) || 0,
                toItem: Number(pagination.to_item) || 0,
            };
        },

        reloadCurrentView(page = this.state.pagination.page) {
            if (this.state.isSearchMode && this.state.searchQuery.length >= 2) {
                return this.search(this.state.searchQuery, page);
            }

            return this.loadBrowser(this.state.currentFolder, page);
        },

        getStoredBrowserPerPage() {
            const storage = this.getLocalStorage();
            const defaultPerPage = Number(mstvConfig.browserPerPage || 50);

            if (!storage) {
                return defaultPerPage;
            }

            const rawValue = storage.getItem('pdmBrowserPerPage');
            const parsedValue = parseInt(rawValue, 10);

            if (!this.isValidPerPage(parsedValue)) {
                return defaultPerPage;
            }

            return parsedValue;
        },

        setStoredBrowserPerPage(value) {
            const storage = this.getLocalStorage();

            if (!storage) {
                return;
            }

            storage.setItem('pdmBrowserPerPage', String(value));
        },

        getLocalStorage() {
            try {
                return window.localStorage;
            } catch (error) {
                return null;
            }
        },

        isValidPerPage(value) {
            return [25, 50, 100, 200].includes(value);
        },

        async apiGet(endpoint) {
            const response = await fetch(this.buildFreshApiUrl(endpoint), {
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mstvConfig.restNonce,
                },
            });
            return this.parseApiResponse(response);
        },

        async apiPost(endpoint, data) {
            const response = await fetch(this.buildApiUrl(endpoint), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mstvConfig.restNonce,
                },
                body: JSON.stringify(data),
            });
            return this.parseApiResponse(response);
        },

        async apiPatch(endpoint, data) {
            const response = await fetch(this.buildApiUrl(endpoint), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mstvConfig.restNonce,
                },
                body: JSON.stringify(data),
            });
            return this.parseApiResponse(response);
        },

        async apiPut(endpoint, data) {
            const response = await fetch(this.buildApiUrl(endpoint), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mstvConfig.restNonce,
                },
                body: JSON.stringify(data),
            });
            return this.parseApiResponse(response);
        },

        async apiDelete(endpoint) {
            const response = await fetch(this.buildApiUrl(endpoint), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mstvConfig.restNonce,
                },
            });
            return this.parseApiResponse(response);
        },

        // Permission helpers. When the backend sends no permission map (e.g. a vault with
        // no rules, or older responses) every action is allowed, preserving prior behavior.
        can(action) {
            const perms = this.state.permissions;
            return !perms || perms[action] !== false;
        },

        folderCan(folder, action) {
            const perms = folder && folder.permissions;
            return !perms || perms[action] !== false;
        },

        applyToolbarPermissions() {
            const toggle = (el, allowed) => {
                if (!el) return;
                el.disabled = !allowed;
                el.classList.toggle('pdm-is-hidden', !allowed);
            };

            toggle(this.elements.uploadBtn, this.can('upload'));
            toggle(this.elements.newFolderBtn, this.can('manage'));
            toggle(this.elements.exportBtn, this.can('download'));
        },

        async openFolderPermissions(folderId, folderName) {
            try {
                const res = await this.apiGet(`folders/${folderId}/permissions`);
                if (!res.success) {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                    return;
                }

                this._perm = {
                    folderId,
                    folderName: folderName || '',
                    actions: res.data.available_actions,
                    defaultAccessOpen: !!res.data.default_access_open,
                    groups: res.data.groups || [],
                    rules: (res.data.rules || []).map(r => ({
                        principal_type: r.principal_type,
                        principal_id: r.principal_id,
                        principal_label: r.principal_label,
                        actions: new Set(r.actions || []),
                    })),
                };

                this.renderPermissionsModal();
            } catch (e) {
                this.showToast(mstvConfig.i18n.errorGeneric, 'error');
            }
        },
    };

    window.PDM = PDM;
})();
