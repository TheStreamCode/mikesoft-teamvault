(function() {
    'use strict';

    if (!window.PDM) {
        return;
    }

    Object.assign(window.PDM, {
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

            const warning = p.defaultAccessOpen
                ? `<div class="pdm-perm-warning" role="alert">${i18n.permDefaultOpenWarning}</div>`
                : '';

            const body = `
                ${warning}
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
                if (!res.success) {
                    body.innerHTML = `<p class="pdm-perm-empty">${this.escapeHtml(res.message || i18n.errorGeneric)}</p>`;
                    return;
                }
                const items = res.data?.items || [];
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
                body.innerHTML = `<p class="pdm-perm-empty">${this.escapeHtml(e.message || i18n.errorGeneric)}</p>`;
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
    });
})();
