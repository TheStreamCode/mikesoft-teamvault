(function() {
    'use strict';

    if (!window.PDM) {
        return;
    }

    Object.assign(window.PDM, {
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
    });

    document.addEventListener('DOMContentLoaded', () => {
        window.PDM.applyBranding();

        if (document.getElementById('pdm-app')) {
            window.PDM.init();
        }

        if (document.querySelector('.pdm-settings-wrapper')) {
            window.PDM.initSettingsPage();
        }

        if (document.getElementById('mstv-groups-app')) {
            window.PDM.initGroupsPage();
        }

        if (document.getElementById('mstv-quotas-app')) {
            window.PDM.initQuotasPage();
        }

        if (document.getElementById('mstv-reports-app')) {
            window.PDM.initReportsPage();
        }

        if (document.getElementById('mstv-notifications-app')) {
            window.PDM.initNotificationsPage();
        }
    });
})();
