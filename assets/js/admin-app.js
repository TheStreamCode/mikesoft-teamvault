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
                const emptyTitle = this.state.isSearchMode ? mstvConfig.i18n.noResults : mstvConfig.i18n.emptyState;
                const emptyDescription = this.state.isSearchMode ? mstvConfig.i18n.searchNoResultsDesc : mstvConfig.i18n.emptyStateDesc;

                this.elements.content.innerHTML = `
                    <div class="pdm-empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                        <h3>${emptyTitle}</h3>
                        <p>${emptyDescription}</p>
                    </div>
                `;
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
                        <div class="pdm-item-name">${this.escapeHtml(folder.name)}</div>
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
                        <div class="pdm-item-meta">${files.file_size_formatted}</div>
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
                            <div class="pdm-list-item-name">${this.escapeHtml(folder.name)}</div>
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
                            <div class="pdm-list-item-meta">${files.file_size_formatted} · ${files.created_at_human}${statusText}</div>
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
                            <span class="pdm-details-row-value">${files.file_size_formatted}</span>
                        </div>
                        <div class="pdm-details-row">
                            <span class="pdm-details-row-label">${mstvConfig.i18n.created}</span>
                            <span class="pdm-details-row-value">${files.created_at_human}</span>
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
                            <span class="pdm-details-row-value">${folder.created_at_human}</span>
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
            
            const iconSvg = type === 'success' 
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : type === 'error'
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

            toast.innerHTML = `
                <span class="pdm-toast-icon pdm-toast-icon--${type}">${iconSvg}</span>
                <span class="pdm-toast-message">${this.escapeHtml(message)}</span>
                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-toast-close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

        renderPermissionsModal() {
            const p = this._perm;
            const i18n = mstvConfig.i18n;

            const actionLabels = {
                view: i18n.permView,
                upload: i18n.permUpload,
                download: i18n.permDownload,
                delete: i18n.permDelete,
                manage: i18n.permManage,
            };

            const head = p.actions.map(a => `<th>${actionLabels[a] || a}</th>`).join('');

            const rows = p.rules.length ? p.rules.map((rule, idx) => {
                const cells = p.actions.map(a => `
                    <td style="text-align:center">
                        <input type="checkbox" data-rule-index="${idx}" data-action="${a}" ${rule.actions.has(a) ? 'checked' : ''}>
                    </td>`).join('');
                const typeLabel = rule.principal_type === 'group' ? i18n.permGroup : i18n.permUser;
                return `
                    <tr>
                        <td><span class="pdm-perm-principal">${this.escapeHtml(rule.principal_label)}</span> <span class="pdm-perm-type">${typeLabel}</span></td>
                        ${cells}
                        <td style="text-align:center">
                            <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-perm-remove" data-rule-index="${idx}" title="${i18n.remove}" aria-label="${i18n.remove}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </td>
                    </tr>`;
            }).join('') : `<tr><td colspan="${p.actions.length + 2}" class="pdm-perm-empty">${i18n.permNoRules}</td></tr>`;

            const groupOptions = p.groups.map(g => `<option value="${g.id}">${this.escapeHtml(g.name)}</option>`).join('');

            const body = `
                <p class="pdm-field-desc">${i18n.permIntro}</p>
                <div class="pdm-perm-table-wrap">
                    <table class="pdm-perm-table">
                        <thead><tr><th>${i18n.permPrincipal}</th>${head}<th></th></tr></thead>
                        <tbody class="pdm-perm-rows">${rows}</tbody>
                    </table>
                </div>
                <div class="pdm-perm-add">
                    <select class="pdm-select pdm-perm-add-type">
                        <option value="user">${i18n.permUser}</option>
                        <option value="group">${i18n.permGroup}</option>
                    </select>
                    <div class="pdm-perm-add-user">
                        <input type="text" class="pdm-input pdm-perm-user-search" placeholder="${i18n.searchUsers}" autocomplete="off">
                        <div class="pdm-perm-user-results"></div>
                    </div>
                    <div class="pdm-perm-add-group pdm-is-hidden">
                        <select class="pdm-select pdm-perm-group-select">${groupOptions || `<option value="">${i18n.permNoGroups}</option>`}</select>
                        <button type="button" class="pdm-btn pdm-btn-secondary pdm-perm-add-group-btn">${i18n.permAdd}</button>
                    </div>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-ghost pdm-perm-reset">${i18n.permReset}</button>
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-perm-save">${i18n.permSave}</button>
            `;

            this.showModal(`${i18n.permTitle}${p.folderName ? ' — ' + p.folderName : ''}`, body, footer);
            this.bindPermissionsModal();
        },

        bindPermissionsModal() {
            const modal = this.elements.modal;
            const p = this._perm;

            modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());

            modal.querySelectorAll('.pdm-perm-rows input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const idx = parseInt(cb.dataset.ruleIndex, 10);
                    const action = cb.dataset.action;
                    if (!p.rules[idx]) return;
                    if (cb.checked) {
                        p.rules[idx].actions.add(action);
                    } else {
                        p.rules[idx].actions.delete(action);
                    }
                });
            });

            modal.querySelectorAll('.pdm-perm-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.ruleIndex, 10);
                    p.rules.splice(idx, 1);
                    this.renderPermissionsModal();
                });
            });

            const typeSelect = modal.querySelector('.pdm-perm-add-type');
            const userBox = modal.querySelector('.pdm-perm-add-user');
            const groupBox = modal.querySelector('.pdm-perm-add-group');
            typeSelect?.addEventListener('change', () => {
                const isGroup = typeSelect.value === 'group';
                userBox?.classList.toggle('pdm-is-hidden', isGroup);
                groupBox?.classList.toggle('pdm-is-hidden', !isGroup);
            });

            const userSearch = modal.querySelector('.pdm-perm-user-search');
            const results = modal.querySelector('.pdm-perm-user-results');
            const runSearch = this.debounce(async () => {
                const q = userSearch.value.trim();
                if (q.length < 2) { results.innerHTML = ''; return; }
                try {
                    const res = await this.apiGet(`users/search?q=${encodeURIComponent(q)}`);
                    results.innerHTML = (res.data || []).map(u =>
                        `<button type="button" class="pdm-perm-user-option" data-id="${u.id}" data-label="${this.escapeHtml(u.display_name)}">${this.escapeHtml(u.display_name)} <span class="pdm-perm-type">${this.escapeHtml(u.login)}</span></button>`
                    ).join('');
                    results.querySelectorAll('.pdm-perm-user-option').forEach(opt => {
                        opt.addEventListener('click', () => {
                            this.addPermissionPrincipal('user', parseInt(opt.dataset.id, 10), opt.dataset.label);
                        });
                    });
                } catch (e) { /* ignore search errors */ }
            }, 300);
            userSearch?.addEventListener('input', runSearch);

            modal.querySelector('.pdm-perm-add-group-btn')?.addEventListener('click', () => {
                const sel = modal.querySelector('.pdm-perm-group-select');
                const id = parseInt(sel.value, 10);
                if (!id) return;
                const label = sel.options[sel.selectedIndex]?.text || ('#' + id);
                this.addPermissionPrincipal('group', id, label);
            });

            modal.querySelector('.pdm-perm-save')?.addEventListener('click', () => this.savePermissions());
            modal.querySelector('.pdm-perm-reset')?.addEventListener('click', () => this.resetPermissions());
        },

        addPermissionPrincipal(type, id, label) {
            const p = this._perm;
            if (!id) return;
            const exists = p.rules.some(r => r.principal_type === type && r.principal_id === id);
            if (exists) {
                this.showToast(mstvConfig.i18n.permAlreadyAdded, 'warning');
                return;
            }
            p.rules.push({ principal_type: type, principal_id: id, principal_label: label, actions: new Set(['view']) });
            this.renderPermissionsModal();
        },

        async savePermissions() {
            const p = this._perm;
            const rules = p.rules.map(r => ({
                principal_type: r.principal_type,
                principal_id: r.principal_id,
                actions: Array.from(r.actions),
            }));
            try {
                const res = await this.apiPut(`folders/${p.folderId}/permissions`, { rules });
                if (res.success) {
                    this.hideModal();
                    this.showToast(mstvConfig.i18n.permSaved, 'success');
                    this.loadBrowser(this.state.currentFolder, this.state.pagination.page);
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        async resetPermissions() {
            const p = this._perm;
            const ok = await this.confirmDialog(mstvConfig.i18n.permResetConfirm, { danger: true, confirmLabel: mstvConfig.i18n.permReset });
            if (!ok) return;
            try {
                const res = await this.apiDelete(`folders/${p.folderId}/permissions`);
                if (res.success) {
                    this.showToast(mstvConfig.i18n.permSaved, 'success');
                    this.loadBrowser(this.state.currentFolder, this.state.pagination.page);
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        // ----- Groups admin page --------------------------------------------

        initGroupsPage() {
            this.cacheModalElements();
            this._groupsRoot = document.getElementById('mstv-groups-app');
            document.getElementById('mstv-groups-new')?.addEventListener('click', () => this.showGroupModal(null));
            this.loadGroups();
        },

        // The groups/reports pages do not include the file-manager modal markup, so build a
        // shared modal/toast container on demand and point the existing helpers at it.
        cacheModalElements() {
            if (this.elements.modal) return;

            if (!document.getElementById('pdm-modal')) {
                const wrap = document.createElement('div');
                wrap.innerHTML = `
                    <div class="pdm-modal" id="pdm-modal" role="dialog" aria-modal="true" aria-labelledby="pdm-modal-title">
                        <div class="pdm-modal-backdrop"></div>
                        <div class="pdm-modal-content">
                            <div class="pdm-modal-header">
                                <h3 class="pdm-modal-title" id="pdm-modal-title"></h3>
                                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-modal-close" aria-label="${mstvConfig.i18n.close}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="pdm-modal-body" id="pdm-modal-body"></div>
                            <div class="pdm-modal-footer" id="pdm-modal-footer"></div>
                        </div>
                    </div>
                    <div class="pdm-toast-container" id="pdm-toast-container"></div>
                `;
                document.body.appendChild(wrap);
            }

            this.elements.modal = document.getElementById('pdm-modal');
            this.elements.modalTitle = document.getElementById('pdm-modal-title');
            this.elements.modalBody = document.getElementById('pdm-modal-body');
            this.elements.modalFooter = document.getElementById('pdm-modal-footer');
            this.elements.toastContainer = document.getElementById('pdm-toast-container');

            this.elements.modal.querySelector('.pdm-modal-close')?.addEventListener('click', () => this.hideModal());
            this.elements.modal.querySelector('.pdm-modal-backdrop')?.addEventListener('click', () => this.hideModal());

            // Close the on-demand governance modal with the Escape key (bound once).
            if (!this._modalEscBound) {
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.elements.modal && this.elements.modal.classList.contains('active')) {
                        this.hideModal();
                    }
                });
                this._modalEscBound = true;
            }
        },

        async loadGroups() {
            try {
                const res = await this.apiGet('groups');
                this._groups = res.success ? res.data : [];
                this.renderGroups();
            } catch (e) {
                this._groupsRoot.querySelector('.mstv-groups-list').innerHTML = `<p class="pdm-perm-empty">${mstvConfig.i18n.errorGeneric}</p>`;
            }
        },

        renderGroups() {
            const i18n = mstvConfig.i18n;
            const list = this._groupsRoot.querySelector('.mstv-groups-list');
            if (!this._groups.length) {
                list.innerHTML = `<p class="pdm-perm-empty">${i18n.groupsEmpty}</p>`;
                return;
            }

            list.innerHTML = this._groups.map(g => `
                <div class="mstv-group-card" data-group-id="${g.id}">
                    <div class="mstv-group-card-main">
                        <div class="mstv-group-name">${this.escapeHtml(g.name)}</div>
                        ${g.description ? `<div class="mstv-group-desc">${this.escapeHtml(g.description)}</div>` : ''}
                        <div class="mstv-group-meta">${g.member_count} ${i18n.groupsMembers}</div>
                    </div>
                    <div class="mstv-group-card-actions">
                        <button type="button" class="pdm-btn pdm-btn-secondary mstv-group-edit" data-id="${g.id}">${i18n.rename}</button>
                        <button type="button" class="pdm-btn pdm-btn-danger mstv-group-delete" data-id="${g.id}">${i18n.delete}</button>
                    </div>
                </div>
            `).join('');

            list.querySelectorAll('.mstv-group-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    const group = this._groups.find(g => g.id === parseInt(btn.dataset.id, 10));
                    this.showGroupModal(group);
                });
            });

            list.querySelectorAll('.mstv-group-delete').forEach(btn => {
                btn.addEventListener('click', () => this.deleteGroup(parseInt(btn.dataset.id, 10)));
            });
        },

        showGroupModal(group) {
            const i18n = mstvConfig.i18n;
            this._groupMembers = group ? group.members.map(m => ({ id: m.id, display_name: m.display_name })) : [];

            const body = `
                <div class="pdm-field">
                    <label class="pdm-field-label" for="mstv-group-name">${i18n.groupsName}</label>
                    <input type="text" class="pdm-input" id="mstv-group-name" value="${group ? this.escapeHtml(group.name) : ''}">
                </div>
                <div class="pdm-field">
                    <label class="pdm-field-label" for="mstv-group-desc">${i18n.groupsDescription}</label>
                    <input type="text" class="pdm-input" id="mstv-group-desc" value="${group ? this.escapeHtml(group.description || '') : ''}">
                </div>
                <div class="pdm-field">
                    <label class="pdm-field-label">${i18n.groupsMembers}</label>
                    <input type="text" class="pdm-input mstv-group-user-search" placeholder="${i18n.searchUsers}" autocomplete="off">
                    <div class="pdm-perm-user-results mstv-group-user-results"></div>
                    <div class="mstv-group-chips"></div>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary mstv-group-save">${i18n.confirm}</button>
            `;

            this.showModal(group ? i18n.groupsEdit : i18n.groupsNew, body, footer);

            const modal = this.elements.modal;
            modal.querySelector('.pdm-modal-cancel')?.addEventListener('click', () => this.hideModal());
            this.renderGroupChips();

            const search = modal.querySelector('.mstv-group-user-search');
            const results = modal.querySelector('.mstv-group-user-results');
            const run = this.debounce(async () => {
                const q = search.value.trim();
                if (q.length < 2) { results.innerHTML = ''; return; }
                try {
                    const res = await this.apiGet(`users/search?q=${encodeURIComponent(q)}`);
                    results.innerHTML = (res.data || []).map(u =>
                        `<button type="button" class="pdm-perm-user-option" data-id="${u.id}" data-label="${this.escapeHtml(u.display_name)}">${this.escapeHtml(u.display_name)}</button>`
                    ).join('');
                    results.querySelectorAll('.pdm-perm-user-option').forEach(opt => {
                        opt.addEventListener('click', () => {
                            const id = parseInt(opt.dataset.id, 10);
                            if (!this._groupMembers.some(m => m.id === id)) {
                                this._groupMembers.push({ id, display_name: opt.dataset.label });
                                this.renderGroupChips();
                            }
                            search.value = '';
                            results.innerHTML = '';
                        });
                    });
                } catch (e) { /* ignore */ }
            }, 300);
            search?.addEventListener('input', run);

            modal.querySelector('.mstv-group-save')?.addEventListener('click', () => this.saveGroup(group ? group.id : null));
        },

        renderGroupChips() {
            const wrap = this.elements.modal.querySelector('.mstv-group-chips');
            if (!wrap) return;
            wrap.innerHTML = this._groupMembers.map(m =>
                `<span class="mstv-chip">${this.escapeHtml(m.display_name)} <button type="button" class="mstv-chip-remove" data-id="${m.id}" aria-label="${mstvConfig.i18n.remove}"><span aria-hidden="true">&times;</span></button></span>`
            ).join('');
            wrap.querySelectorAll('.mstv-chip-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = parseInt(btn.dataset.id, 10);
                    this._groupMembers = this._groupMembers.filter(m => m.id !== id);
                    this.renderGroupChips();
                });
            });
        },

        async saveGroup(groupId) {
            const name = this.elements.modal.querySelector('#mstv-group-name').value.trim();
            const description = this.elements.modal.querySelector('#mstv-group-desc').value.trim();
            if (!name) {
                this.showToast(mstvConfig.i18n.groupsNameRequired, 'error');
                return;
            }
            const members = this._groupMembers.map(m => m.id);
            try {
                const res = groupId
                    ? await this.apiPatch(`groups/${groupId}`, { name, description, members })
                    : await this.apiPost('groups', { name, description, members });
                if (res.success) {
                    this.hideModal();
                    this.showToast(mstvConfig.i18n.groupsSaved, 'success');
                    this.loadGroups();
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        async deleteGroup(groupId) {
            const ok = await this.confirmDialog(mstvConfig.i18n.groupsDeleteConfirm, { danger: true, confirmLabel: mstvConfig.i18n.delete });
            if (!ok) return;
            try {
                const res = await this.apiDelete(`groups/${groupId}`);
                if (res.success) {
                    this.showToast(mstvConfig.i18n.groupsDeleted, 'success');
                    this.loadGroups();
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        // ----- Quotas admin page --------------------------------------------

        initQuotasPage() {
            this.cacheModalElements();
            this._quotasRoot = document.getElementById('mstv-quotas-app');
            this.loadQuotas();
        },

        async loadQuotas() {
            try {
                const res = await this.apiGet('quotas');
                this._quota = res.success ? res.data : { enabled: false, items: [], groups: [] };
                this._quota.items = this._quota.items.map(it => ({ ...it }));
                this.renderQuotas();
            } catch (e) {
                this._quotasRoot.querySelector('.mstv-quotas-body').innerHTML = `<p class="pdm-perm-empty">${mstvConfig.i18n.errorGeneric}</p>`;
            }
        },

        renderQuotas() {
            const i18n = mstvConfig.i18n;
            const q = this._quota;

            const enabled = this._quotasRoot.querySelector('#mstv-quotas-enabled');
            enabled.checked = !!q.enabled;
            enabled.onchange = () => { q.enabled = enabled.checked; };

            const rows = q.items.length ? q.items.map((it, idx) => `
                <tr>
                    <td><span class="pdm-perm-principal">${this.escapeHtml(it.label)}</span> <span class="pdm-perm-type">${it.principal_type === 'group' ? i18n.permGroup : i18n.permUser}</span></td>
                    <td><input type="number" min="1" step="1" class="pdm-input mstv-quota-mb" data-idx="${idx}" value="${Math.max(1, Math.round(it.max_bytes / 1048576))}"> <span class="pdm-perm-type">MB</span></td>
                    <td>${this.formatBytes(it.used_bytes || 0)}</td>
                    <td style="text-align:center"><button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost mstv-quota-remove" data-idx="${idx}" aria-label="${i18n.remove}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></td>
                </tr>`).join('') : `<tr><td colspan="4" class="pdm-perm-empty">${i18n.quotasEmpty}</td></tr>`;

            const groupOptions = (q.groups || []).map(g => `<option value="${g.id}">${this.escapeHtml(g.name)}</option>`).join('');

            this._quotasRoot.querySelector('.mstv-quotas-body').innerHTML = `
                <div class="pdm-perm-table-wrap">
                    <table class="pdm-perm-table">
                        <thead><tr><th>${i18n.permPrincipal}</th><th>${i18n.quotasLimit}</th><th>${i18n.quotasUsed}</th><th></th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div class="pdm-perm-add">
                    <select class="pdm-select mstv-quota-add-type">
                        <option value="user">${i18n.permUser}</option>
                        <option value="group">${i18n.permGroup}</option>
                    </select>
                    <div class="pdm-perm-add-user mstv-quota-user-box">
                        <input type="text" class="pdm-input mstv-quota-user-search" placeholder="${i18n.searchUsers}" autocomplete="off">
                        <div class="pdm-perm-user-results mstv-quota-user-results"></div>
                    </div>
                    <div class="pdm-perm-add-group mstv-quota-group-box pdm-is-hidden">
                        <select class="pdm-select mstv-quota-group-select">${groupOptions || `<option value="">${i18n.permNoGroups}</option>`}</select>
                        <button type="button" class="pdm-btn pdm-btn-secondary mstv-quota-add-group-btn">${i18n.permAdd}</button>
                    </div>
                </div>
                <div class="mstv-governance-footer">
                    <button type="button" class="pdm-btn pdm-btn-primary mstv-quotas-save">${i18n.quotasSave}</button>
                </div>
            `;

            this.bindQuotas();
        },

        bindQuotas() {
            const root = this._quotasRoot;
            const q = this._quota;

            root.querySelectorAll('.mstv-quota-mb').forEach(inp => {
                inp.addEventListener('input', () => {
                    const idx = parseInt(inp.dataset.idx, 10);
                    const mb = Math.max(1, parseInt(inp.value, 10) || 1);
                    q.items[idx].max_bytes = mb * 1048576;
                });
            });

            root.querySelectorAll('.mstv-quota-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    q.items.splice(parseInt(btn.dataset.idx, 10), 1);
                    this.renderQuotas();
                });
            });

            const typeSel = root.querySelector('.mstv-quota-add-type');
            typeSel.addEventListener('change', () => {
                const isGroup = typeSel.value === 'group';
                root.querySelector('.mstv-quota-user-box').classList.toggle('pdm-is-hidden', isGroup);
                root.querySelector('.mstv-quota-group-box').classList.toggle('pdm-is-hidden', !isGroup);
            });

            const search = root.querySelector('.mstv-quota-user-search');
            const results = root.querySelector('.mstv-quota-user-results');
            const run = this.debounce(async () => {
                const term = search.value.trim();
                if (term.length < 2) { results.innerHTML = ''; return; }
                try {
                    const res = await this.apiGet(`users/search?q=${encodeURIComponent(term)}`);
                    results.innerHTML = (res.data || []).map(u =>
                        `<button type="button" class="pdm-perm-user-option" data-id="${u.id}" data-label="${this.escapeHtml(u.display_name)}">${this.escapeHtml(u.display_name)}</button>`
                    ).join('');
                    results.querySelectorAll('.pdm-perm-user-option').forEach(opt => {
                        opt.addEventListener('click', () => this.addQuotaItem('user', parseInt(opt.dataset.id, 10), opt.dataset.label));
                    });
                } catch (e) { /* ignore */ }
            }, 300);
            search.addEventListener('input', run);

            root.querySelector('.mstv-quota-add-group-btn')?.addEventListener('click', () => {
                const sel = root.querySelector('.mstv-quota-group-select');
                const id = parseInt(sel.value, 10);
                if (!id) return;
                this.addQuotaItem('group', id, sel.options[sel.selectedIndex]?.text || ('#' + id));
            });

            root.querySelector('.mstv-quotas-save')?.addEventListener('click', () => this.saveQuotas());
        },

        addQuotaItem(type, id, label) {
            const q = this._quota;
            if (!id) return;
            if (q.items.some(it => it.principal_type === type && it.principal_id === id)) {
                this.showToast(mstvConfig.i18n.permAlreadyAdded, 'warning');
                return;
            }
            q.items.push({ principal_type: type, principal_id: id, label, max_bytes: 104857600, used_bytes: 0 });
            this.renderQuotas();
        },

        async saveQuotas() {
            const q = this._quota;
            const payload = {
                enabled: !!q.enabled,
                items: q.items.map(it => ({ principal_type: it.principal_type, principal_id: it.principal_id, max_bytes: it.max_bytes })),
            };
            try {
                const res = await this.apiPut('quotas', payload);
                if (res.success) {
                    this.showToast(mstvConfig.i18n.quotasSaved, 'success');
                    this.loadQuotas();
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        // ----- Reports admin page -------------------------------------------

        initReportsPage() {
            this.cacheModalElements();
            this._reportsRoot = document.getElementById('mstv-reports-app');
            this._reportsRoot.querySelector('#mstv-report-apply')?.addEventListener('click', () => this.loadReport());
            this._reportsRoot.querySelector('#mstv-report-csv')?.addEventListener('click', () => this.downloadAuditCsv());
        },

        reportFilters() {
            const r = this._reportsRoot;
            return {
                group_by: r.querySelector('#mstv-report-group').value,
                date_from: r.querySelector('#mstv-report-from').value,
                date_to: r.querySelector('#mstv-report-to').value,
                action: r.querySelector('#mstv-report-action').value,
            };
        },

        async loadReport() {
            const i18n = mstvConfig.i18n;
            const f = this.reportFilters();
            const params = new URLSearchParams();
            Object.entries(f).forEach(([k, v]) => { if (v) params.set(k, v); });
            const body = this._reportsRoot.querySelector('.mstv-reports-body');
            const applyBtn = this._reportsRoot.querySelector('#mstv-report-apply');
            if (applyBtn) applyBtn.disabled = true;
            body.innerHTML = `<div class="pdm-loading"><div class="pdm-spinner"></div><span>${i18n.loading}</span></div>`;
            try {
                const res = await this.apiGet(`reports/access?${params}`);
                const items = res.data.items || [];
                if (!items.length) {
                    body.innerHTML = `<p class="pdm-perm-empty">${i18n.noResults}</p>`;
                    return;
                }
                const groupHead = f.group_by === 'file' ? i18n.file : (f.group_by === 'folder' ? i18n.folder : i18n.permUser);
                body.innerHTML = `
                    <div class="pdm-perm-table-wrap">
                        <table class="pdm-perm-table">
                            <thead><tr><th>${groupHead}</th><th>${i18n.reportEvents}</th><th>${i18n.reportLastAccess}</th></tr></thead>
                            <tbody>${items.map(it => `
                                <tr>
                                    <td>${this.escapeHtml(it.label)}</td>
                                    <td>${it.events}</td>
                                    <td>${this.escapeHtml(it.last_access || '')}</td>
                                </tr>`).join('')}</tbody>
                        </table>
                    </div>`;
            } catch (e) {
                body.innerHTML = `<p class="pdm-perm-empty">${e.message || i18n.errorGeneric}</p>`;
            } finally {
                if (applyBtn) applyBtn.disabled = false;
            }
        },

        downloadAuditCsv() {
            const f = this.reportFilters();
            const params = new URLSearchParams({
                action: 'mstv_export_audit_csv',
                mstv_audit_csv_nonce: mstvConfig.auditCsvNonce,
            });
            if (f.date_from) params.set('date_from', f.date_from);
            if (f.date_to) params.set('date_to', f.date_to);
            // The report "action" select is preview/download; pass it through as a log action filter.
            if (f.action) params.set('action', f.action);
            window.location.href = `${mstvConfig.actionUrl}?${params}`;
        },

        // ----- Notifications admin page -------------------------------------

        initNotificationsPage() {
            this.cacheModalElements();
            this._notifRoot = document.getElementById('mstv-notifications-app');
            this.loadNotifications();
        },

        async loadNotifications() {
            try {
                const res = await this.apiGet('settings/notifications');
                this._notif = res.success ? res.data : null;
                if (this._notif) {
                    this._notif.recipients = this._notif.recipients || { admins: true, users: [], groups: [] };
                    this.renderNotifications();
                }
            } catch (e) {
                this._notifRoot.querySelector('.mstv-notifications-body').innerHTML = `<p class="pdm-perm-empty">${mstvConfig.i18n.errorGeneric}</p>`;
            }
        },

        renderNotifications() {
            const i18n = mstvConfig.i18n;
            const n = this._notif;
            const eventLabels = { upload: i18n.permUpload, download: i18n.permDownload, delete: i18n.delete, access_denied: i18n.notifAccessDenied };

            const eventBoxes = n.available_events.map(ev => `
                <label class="pdm-checkbox-label">
                    <input type="checkbox" class="mstv-notif-event" value="${ev}" ${n.events.includes(ev) ? 'checked' : ''}>
                    <span class="pdm-checkbox-text">${eventLabels[ev] || ev}</span>
                </label>`).join('');

            const groupBoxes = (n.groups || []).map(g => `
                <label class="pdm-checkbox-label">
                    <input type="checkbox" class="mstv-notif-group" value="${g.id}" ${n.recipients.groups.includes(g.id) ? 'checked' : ''}>
                    <span class="pdm-checkbox-text">${this.escapeHtml(g.name)}</span>
                </label>`).join('') || `<p class="pdm-field-desc">${i18n.permNoGroups}</p>`;

            this._notifRoot.querySelector('.mstv-notifications-body').innerHTML = `
                <label class="pdm-checkbox-label mstv-quotas-enable">
                    <input type="checkbox" id="mstv-notif-enabled" ${n.enabled ? 'checked' : ''}>
                    <span class="pdm-checkbox-text">${i18n.notifEnable}</span>
                </label>

                <div class="pdm-settings-section">
                    <h2 class="pdm-section-title">${i18n.notifEvents}</h2>
                    <div class="mstv-notif-events">${eventBoxes}</div>
                </div>

                <div class="pdm-settings-section">
                    <h2 class="pdm-section-title">${i18n.notifRecipients}</h2>
                    <label class="pdm-checkbox-label">
                        <input type="checkbox" id="mstv-notif-admins" ${n.recipients.admins ? 'checked' : ''}>
                        <span class="pdm-checkbox-text">${i18n.notifAdmins}</span>
                    </label>
                    <div class="pdm-field">
                        <label class="pdm-field-label">${i18n.notifUsers}</label>
                        <div class="pdm-perm-add-user" style="max-width:360px">
                            <input type="text" class="pdm-input mstv-notif-user-search" placeholder="${i18n.searchUsers}" autocomplete="off">
                            <div class="pdm-perm-user-results mstv-notif-user-results"></div>
                        </div>
                        <div class="mstv-group-chips mstv-notif-chips"></div>
                    </div>
                    <div class="pdm-field">
                        <label class="pdm-field-label">${i18n.notifGroups}</label>
                        <div class="mstv-notif-groups">${groupBoxes}</div>
                    </div>
                </div>

                <div class="mstv-governance-footer">
                    <button type="button" class="pdm-btn pdm-btn-primary mstv-notif-save">${i18n.notifSave}</button>
                </div>
            `;

            this.bindNotifications();
        },

        bindNotifications() {
            const root = this._notifRoot;
            const n = this._notif;

            root.querySelector('#mstv-notif-enabled').addEventListener('change', e => { n.enabled = e.target.checked; });
            root.querySelector('#mstv-notif-admins').addEventListener('change', e => { n.recipients.admins = e.target.checked; });

            root.querySelectorAll('.mstv-notif-event').forEach(cb => cb.addEventListener('change', () => {
                n.events = Array.from(root.querySelectorAll('.mstv-notif-event:checked')).map(c => c.value);
            }));
            root.querySelectorAll('.mstv-notif-group').forEach(cb => cb.addEventListener('change', () => {
                n.recipients.groups = Array.from(root.querySelectorAll('.mstv-notif-group:checked')).map(c => parseInt(c.value, 10));
            }));

            this.renderNotifChips();

            const search = root.querySelector('.mstv-notif-user-search');
            const results = root.querySelector('.mstv-notif-user-results');
            const run = this.debounce(async () => {
                const term = search.value.trim();
                if (term.length < 2) { results.innerHTML = ''; return; }
                try {
                    const res = await this.apiGet(`users/search?q=${encodeURIComponent(term)}`);
                    results.innerHTML = (res.data || []).map(u =>
                        `<button type="button" class="pdm-perm-user-option" data-id="${u.id}" data-label="${this.escapeHtml(u.display_name)}">${this.escapeHtml(u.display_name)}</button>`
                    ).join('');
                    results.querySelectorAll('.pdm-perm-user-option').forEach(opt => opt.addEventListener('click', () => {
                        const id = parseInt(opt.dataset.id, 10);
                        if (!n.recipients.users.some(u => u.id === id)) {
                            n.recipients.users.push({ id, display_name: opt.dataset.label });
                            this.renderNotifChips();
                        }
                        search.value = ''; results.innerHTML = '';
                    }));
                } catch (e) { /* ignore */ }
            }, 300);
            search.addEventListener('input', run);

            root.querySelector('.mstv-notif-save').addEventListener('click', () => this.saveNotifications());
        },

        renderNotifChips() {
            const wrap = this._notifRoot.querySelector('.mstv-notif-chips');
            const n = this._notif;
            wrap.innerHTML = n.recipients.users.map(u =>
                `<span class="mstv-chip">${this.escapeHtml(u.display_name)} <button type="button" class="mstv-chip-remove" data-id="${u.id}" aria-label="${mstvConfig.i18n.remove}"><span aria-hidden="true">&times;</span></button></span>`
            ).join('');
            wrap.querySelectorAll('.mstv-chip-remove').forEach(btn => btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                n.recipients.users = n.recipients.users.filter(u => u.id !== id);
                this.renderNotifChips();
            }));
        },

        async saveNotifications() {
            const n = this._notif;
            const payload = {
                enabled: !!n.enabled,
                events: n.events,
                recipients: {
                    admins: !!n.recipients.admins,
                    users: n.recipients.users.map(u => u.id),
                    groups: n.recipients.groups,
                },
            };
            try {
                const res = await this.apiPost('settings/notifications', payload);
                if (res.success) {
                    this.showToast(mstvConfig.i18n.notifSaved, 'success');
                } else {
                    this.showToast(mstvConfig.i18n.errorGeneric, 'error');
                }
            } catch (e) {
                this.showToast(e.message || mstvConfig.i18n.errorGeneric, 'error');
            }
        },

        async parseApiResponse(response) {
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (!response.ok && !data.success) {
                    throw new Error(data.message || `HTTP ${response.status}`);
                }
                return data;
            }

            const text = await response.text();
            const message = text
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            throw new Error(message || `HTTP ${response.status}`);
        },

        buildApiUrl(endpoint) {
            const base = String(mstvConfig.restUrl || '').replace(/\/+$/, '');
            const path = String(endpoint || '').replace(/^\/+/, '');

            if (base.includes('?')) {
                const qIdx = path.indexOf('?');

                if (qIdx === -1) {
                    return `${base}/${path}`;
                }

                return `${base}/${path.substring(0, qIdx)}&${path.substring(qIdx + 1)}`;
            }

            return `${base}/${path}`;
        },

        buildFreshApiUrl(endpoint) {
            const url = new URL(this.buildApiUrl(endpoint), window.location.href);
            url.searchParams.set('_mstv', String(Date.now()));
            return url.toString();
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        isExternalFileDrag(event) {
            const types = Array.from(event.dataTransfer?.types || []);

            if (!types.includes('Files')) {
                return false;
            }

            return !types.includes('text/plain');
        },

        // Apply the configured white-label accent color as a CSS custom property so the
        // plugin screens pick it up. No-op when branding is disabled or unset.
        applyBranding() {
            const accent = (mstvConfig.branding && mstvConfig.branding.accent) || '';
            if (accent) {
                const root = document.documentElement.style;
                root.setProperty('--pdm-accent', accent);
                // Primary buttons across all plugin screens read --pdm-color-primary.
                root.setProperty('--pdm-color-primary', accent);
                root.setProperty('--pdm-color-primary-hover', accent);
            }
        },

        bindBrandLogoPicker() {
            const button = document.getElementById('mstv-brand-logo-picker');
            const input = document.getElementById('mstv_brand_logo_url');
            if (!button || !input || !window.wp || !window.wp.media) {
                return;
            }

            let frame;
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = window.wp.media({ title: mstvConfig.i18n.brandSelectImage, button: { text: mstvConfig.i18n.brandSelectImage }, multiple: false });
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    input.value = attachment.url;
                });
                frame.open();
            });
        },

        initSettingsPage() {
            this.bindBrandLogoPicker();

            const whitelistCheckbox = document.getElementById('mstv_use_user_whitelist');
            const whitelistField = document.querySelector('.pdm-user-whitelist-field');
            const userSearch = document.getElementById('pdm-user-search');
            const userResults = document.getElementById('pdm-user-results');
            const allowedUsersContainer = document.getElementById('pdm-allowed-users');
            const noUsersMsg = document.getElementById('pdm-no-users');

            if (whitelistCheckbox && whitelistField) {
                whitelistCheckbox.addEventListener('change', () => {
                    whitelistField.classList.toggle('pdm-hidden', !whitelistCheckbox.checked);
                });
            }

            if (userSearch && userResults) {
                userSearch.addEventListener('input', this.debounce(async (e) => {
                    const query = e.target.value.trim();
                    
                    if (query.length < 2) {
                        userResults.classList.remove('active');
                        userResults.innerHTML = '';
                        return;
                    }

                    try {
                        const response = await this.apiGet(`users/search?q=${encodeURIComponent(query)}`);
                        
                        if (response.success && response.data.length > 0) {
                            userResults.innerHTML = response.data.map(user => `
                                <div class="pdm-user-result" data-user-id="${user.id}" data-user-login="${this.escapeHtml(user.login)}" data-user-name="${this.escapeHtml(user.display_name)}">
                                    <div class="pdm-user-result-avatar">${user.display_name.charAt(0).toUpperCase()}</div>
                                    <div class="pdm-user-result-info">
                                        <div class="pdm-user-result-name">${this.escapeHtml(user.display_name)}</div>
                                        <div class="pdm-user-result-login">${this.escapeHtml(user.login)}</div>
                                    </div>
                                </div>
                            `).join('');
                            userResults.classList.add('active');
                        } else {
                            userResults.innerHTML = `<div class="pdm-user-result" style="pointer-events: none; opacity: 0.6;">${mstvConfig.i18n.userNotFound}</div>`;
                            userResults.classList.add('active');
                        }
                    } catch (error) {
                        console.error('User search error:', error);
                    }
                }, 300));

                userResults.addEventListener('click', (e) => {
                    const result = e.target.closest('.pdm-user-result');
                    if (!result) return;

                    const userId = result.dataset.userId;
                    const userLogin = result.dataset.userLogin;
                    const userName = result.dataset.userName;

                    if (allowedUsersContainer.querySelector(`[data-user-id="${userId}"]`)) {
                        alert(mstvConfig.i18n.userAlreadyInList);
                        return;
                    }

                    this.addUserTag(userId, userName, userLogin);
                    userSearch.value = '';
                    userResults.classList.remove('active');
                    userResults.innerHTML = '';
                });

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.pdm-user-search')) {
                        userResults.classList.remove('active');
                    }
                });
            }

            if (allowedUsersContainer) {
                allowedUsersContainer.addEventListener('click', (e) => {
                    const removeBtn = e.target.closest('.pdm-remove-user');
                    if (removeBtn) {
                        const tag = removeBtn.closest('.pdm-user-tag');
                        if (tag) {
                            tag.remove();
                            this.updateNoUsersMessage();
                        }
                    }
                });
            }
        },

        addUserTag(userId, userName, userLogin) {
            const allowedUsersContainer = document.getElementById('pdm-allowed-users');
            const noUsersMsg = document.getElementById('pdm-no-users');

            if (!allowedUsersContainer) return;

            const tag = document.createElement('div');
            tag.className = 'pdm-user-tag';
            tag.dataset.userId = userId;
            tag.innerHTML = `
                <span class="pdm-user-name">${this.escapeHtml(userName)} (${this.escapeHtml(userLogin)})</span>
                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-remove-user" title="${mstvConfig.i18n.remove}" aria-label="${mstvConfig.i18n.remove}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <input type="hidden" name="mstv_allowed_users[]" value="${userId}">
            `;

            allowedUsersContainer.appendChild(tag);

            if (noUsersMsg) {
                noUsersMsg.classList.add('pdm-hidden');
            }
        },

        updateNoUsersMessage() {
            const allowedUsersContainer = document.getElementById('pdm-allowed-users');
            const noUsersMsg = document.getElementById('pdm-no-users');

            if (!allowedUsersContainer || !noUsersMsg) return;

            const userTags = allowedUsersContainer.querySelectorAll('.pdm-user-tag');
            noUsersMsg.classList.toggle('pdm-hidden', userTags.length !== 0);
        },

        showExportModal() {
            const hasFolders = Array.isArray(this.state.folderTree) && this.state.folderTree.length > 0;
            const defaultMode = 'all';
            const preselectedFolders = this.state.currentFolder === null ? [] : [this.state.currentFolder];
            const exportTreeHtml = hasFolders
                ? this.buildExportFolderTreeHtml(this.state.folderTree, new Set(preselectedFolders))
                : `<p class="pdm-export-empty">${mstvConfig.i18n.noFoldersAvailable}</p>`;

            const body = `
                <div class="pdm-export-options">
                    <label class="pdm-export-choice">
                        <input type="radio" name="pdm-export-mode" value="all" ${defaultMode === 'all' ? 'checked' : ''}>
                        <span>
                            <strong>${mstvConfig.i18n.exportAll}</strong>
                            <small>${mstvConfig.i18n.exportAllDesc}</small>
                        </span>
                    </label>
                    <label class="pdm-export-choice ${hasFolders ? '' : 'pdm-export-choice--disabled'}">
                        <input type="radio" name="pdm-export-mode" value="selection" ${hasFolders ? '' : 'disabled'}>
                        <span>
                            <strong>${mstvConfig.i18n.exportSelectedFolders}</strong>
                            <small>${mstvConfig.i18n.exportSelectedFoldersDesc}</small>
                        </span>
                    </label>
                    <div class="pdm-export-folder-tree" ${defaultMode === 'selection' ? '' : 'hidden'}>
                        <div class="pdm-export-folder-tree-inner">
                            ${exportTreeHtml}
                        </div>
                    </div>
                </div>
            `;

            const footer = `
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-modal-cancel">${mstvConfig.i18n.cancel}</button>
                <button type="button" class="pdm-btn pdm-btn-primary pdm-export-confirm">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <span>${mstvConfig.i18n.export}</span>
                </button>
            `;

            this.showModal(mstvConfig.i18n.export, body, footer);

            const confirmBtn = this.elements.modal.querySelector('.pdm-export-confirm');
            const cancelBtn = this.elements.modal.querySelector('.pdm-modal-cancel');
            const modeInputs = this.elements.modal.querySelectorAll('input[name="pdm-export-mode"]');
            const folderTree = this.elements.modal.querySelector('.pdm-export-folder-tree');

            modeInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    if (!folderTree) {
                        return;
                    }

                    folderTree.hidden = input.value !== 'selection' || !input.checked;
                });
            });

            confirmBtn?.addEventListener('click', () => {
                const selectedMode = this.elements.modal.querySelector('input[name="pdm-export-mode"]:checked')?.value || defaultMode;
                const selectedFolderIds = selectedMode === 'selection'
                    ? Array.from(this.elements.modal.querySelectorAll('input[name="pdm-export-folder"]:checked')).map((input) => parseInt(input.value, 10)).filter((id) => Number.isInteger(id) && id > 0)
                    : [];

                if (selectedMode === 'selection' && selectedFolderIds.length === 0) {
                    this.showToast(mstvConfig.i18n.exportNoFoldersSelected, 'warning');
                    return;
                }

                this.hideModal();
                this.startExport({
                    mode: selectedMode,
                    folderIds: selectedFolderIds,
                });
            });

            cancelBtn?.addEventListener('click', () => this.hideModal());
        },

        buildExportFolderTreeHtml(folders, selectedIds = new Set()) {
            if (!Array.isArray(folders) || folders.length === 0) {
                return '';
            }

            return folders.map((folder) => {
                const isChecked = selectedIds.has(folder.id);
                const childrenHtml = folder.children?.length
                    ? `<div class="pdm-export-folder-children">${this.buildExportFolderTreeHtml(folder.children, selectedIds)}</div>`
                    : '';

                return `
                    <div class="pdm-export-folder-node">
                        <label class="pdm-export-folder-option">
                            <input type="checkbox" name="pdm-export-folder" value="${folder.id}" ${isChecked ? 'checked' : ''}>
                            <span>${this.escapeHtml(folder.name)}</span>
                        </label>
                        ${childrenHtml}
                    </div>
                `;
            }).join('');
        },

        startExport(options = {}) {
            const mode = options.mode || 'all';
            const folderIds = Array.isArray(options.folderIds) ? options.folderIds : [];

            this.showToast(mstvConfig.i18n.exporting, 'info');

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = mstvConfig.actionUrl;
            form.target = '_blank';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = mode === 'selection' ? 'mstv_export_selection' : 'mstv_export_all';
            form.appendChild(actionInput);

            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = mode === 'selection' ? 'mstv_export_selection_nonce' : 'mstv_stream_nonce';
            nonceInput.value = mode === 'selection' ? mstvConfig.exportSelectionNonce : mstvConfig.streamNonce;
            form.appendChild(nonceInput);

            if (mode === 'selection') {
                folderIds.forEach((selectedFolderId) => {
                    const folderInput = document.createElement('input');
                    folderInput.type = 'hidden';
                    folderInput.name = 'folder_ids[]';
                    folderInput.value = String(selectedFolderId);
                    form.appendChild(folderInput);
                });
            }

            document.body.appendChild(form);
            form.submit();

            setTimeout(() => {
                document.body.removeChild(form);
                this.showToast(mstvConfig.i18n.exportSuccess, 'success');
            }, 2000);
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        PDM.applyBranding();

        if (document.getElementById('pdm-app')) {
            PDM.init();
        }

        if (document.querySelector('.pdm-settings-wrapper')) {
            PDM.initSettingsPage();
        }

        if (document.getElementById('mstv-groups-app')) {
            PDM.initGroupsPage();
        }

        if (document.getElementById('mstv-quotas-app')) {
            PDM.initQuotasPage();
        }

        if (document.getElementById('mstv-reports-app')) {
            PDM.initReportsPage();
        }

        if (document.getElementById('mstv-notifications-app')) {
            PDM.initNotificationsPage();
        }
    });

    window.PDM = PDM;
})();
