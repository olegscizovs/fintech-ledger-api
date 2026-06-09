import { state } from './state.js';
import { i18n } from './i18n.js';
import { apiRequest } from './api.js';
import { updateTestButtonsState } from './test-suite.js';

// Helper to escape HTML characters to prevent XSS injection
export function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Helper to force browser repaint of elements using -webkit-background-clip: text

export function triggerLogoRepaint() {
    const logoTexts = document.querySelectorAll('.logo h2, .auth-logo h1');
    logoTexts.forEach(el => {
        const originalFill = el.style.webkitTextFillColor;
        el.style.webkitTextFillColor = 'inherit';
        el.offsetHeight; // Force reflow
        el.style.webkitTextFillColor = originalFill;
    });
}

// Language switching application logic
export function setLanguage(lang) {
    if (lang !== 'de' && lang !== 'en') return;
    state.currentLang = lang;
    localStorage.setItem('appLanguage', lang);
    applyTranslations();
}

export function applyTranslations() {
    // 1. Static text replacements
    const elements = document.querySelectorAll('[data-i18n]');
    elements.forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (i18n[state.currentLang] && i18n[state.currentLang][key]) {
            el.textContent = i18n[state.currentLang][key];
        }
    });

    // Translate input placeholders
    const placeholderElements = document.querySelectorAll('[data-i18n-placeholder]');
    placeholderElements.forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (i18n[state.currentLang] && i18n[state.currentLang][key]) {
            el.placeholder = i18n[state.currentLang][key];
        }
    });

    // 2. Active buttons styling update
    document.querySelectorAll('.btn-lang').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeHeaderBtn = document.getElementById(`lang-btn-${state.currentLang}`);
    if (activeHeaderBtn) activeHeaderBtn.classList.add('active');

    document.querySelectorAll('.lang-span').forEach(span => {
        span.classList.remove('active');
    });
    const activeAuthSpan = document.getElementById(`auth-lang-${state.currentLang}`);
    if (activeAuthSpan) activeAuthSpan.classList.add('active');

    // 3. Dynamic submit labels & placeholders
    const submitBtn = document.getElementById('auth-submit-btn');
    if (submitBtn) {
        submitBtn.querySelector('span').textContent = state.authMode === 'login' 
            ? i18n[state.currentLang].loginBtn 
            : i18n[state.currentLang].registerBtn;
    }

    const toggleText = document.getElementById('auth-toggle');
    if (toggleText) {
        if (state.authMode === 'login') {
            toggleText.innerHTML = `${i18n[state.currentLang].toggleRegister.split('?')[0]}? <span onclick="toggleAuthMode()">${i18n[state.currentLang].toggleRegister.split('?')[1]}</span>`;
        } else {
            toggleText.innerHTML = `${i18n[state.currentLang].toggleLogin.split('?')[0]}? <span onclick="toggleAuthMode()">${i18n[state.currentLang].toggleLogin.split('?')[1]}</span>`;
        }
    }

    // Refresh dynamic lists to apply new language strings
    if (state.accounts.length > 0) {
        renderAccountsList();
    }
    calculateDoubleEntryBalance();
    updateCreateAccountSuffix();
    triggerLogoRepaint();
}

// Copy to clipboard helper
export function copyToClipboard(text, key, event) {
    if (event) {
        event.stopPropagation(); // Avoid selecting card
    }
    navigator.clipboard.writeText(text).then(() => {
        showToast(i18n[state.currentLang][key], "info");
    }).catch(err => {
        console.error("Failed to copy text: ", err);
    });
}

// Setup notification banners
export function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '❌';

    toast.innerHTML = `<span>${icon}</span><span style="font-size: 0.9rem;">${escapeHtml(message)}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) reverse forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// View toggle helpers
export function toggleAuthMode() {
    state.authMode = state.authMode === 'login' ? 'register' : 'login';
    applyTranslations();
}

// Auth Submit handler
export async function handleAuthSubmit(event) {
    event.preventDefault();
    const email = document.getElementById('auth-email').value;
    const password = document.getElementById('auth-password').value;
    const submitBtn = document.getElementById('auth-submit-btn');

    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = state.authMode === 'login' 
        ? i18n[state.currentLang].loggingIn 
        : i18n[state.currentLang].registering;

    try {
        if (state.authMode === 'register') {
            await apiRequest('/api/register', 'POST', { email, password }, false);
            showToast(i18n[state.currentLang].welcomeMsg, "success");
        }

        const data = await apiRequest('/api/login', 'POST', { email, password }, false);
        state.jwtToken = data.token;
        state.refreshToken = data.refresh_token;
        state.userEmail = email;

        localStorage.setItem('jwtToken', state.jwtToken);
        localStorage.setItem('refreshToken', state.refreshToken);
        localStorage.setItem('userEmail', email);

        showToast(i18n[state.currentLang].welcomeMsg, "success");
        initDashboard();
    } catch (err) {
        showToast(err.message, "error");
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = state.authMode === 'login' 
            ? i18n[state.currentLang].loginBtn 
            : i18n[state.currentLang].registerBtn;
    }
}

// Handle user logout
export function handleLogout() {
    state.jwtToken = '';
    state.refreshToken = '';
    state.userEmail = '';
    state.accounts = [];
    state.activeAccountUuid = null;
    localStorage.clear();

    document.getElementById('app-container').style.display = 'none';
    document.getElementById('auth-container').style.display = 'flex';
    triggerLogoRepaint();
    document.getElementById('auth-form').reset();
    const submitBtn = document.getElementById('auth-submit-btn');
    if (submitBtn) submitBtn.disabled = false;
    state.authMode = 'register';
    toggleAuthMode();
}

// Initialize dashboard upon login
export function initDashboard() {
    document.getElementById('auth-container').style.display = 'none';
    document.getElementById('app-container').style.display = 'flex';
    document.getElementById('user-display-email').textContent = state.userEmail;

    triggerLogoRepaint();
    if (typeof updateTestButtonsState === 'function') {
        updateTestButtonsState();
    }

    // Reset dashboard state or restore selected wallet from cache
    state.activeAccountUuid = localStorage.getItem('activeAccountUuid');
    if (state.activeAccountUuid) {
        document.getElementById('pdf-compile-btn').disabled = false;
    } else {
        document.getElementById('statement-title').textContent = i18n[state.currentLang].ledgerHistoryTitle;
        document.getElementById('pdf-compile-btn').disabled = true;
        document.getElementById('statement-container').innerHTML = `
            <div class="empty-state">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <p data-i18n="emptyHistoryPrompt">${i18n[state.currentLang].emptyHistoryPrompt}</p>
            </div>
        `;
    }

    loadAccounts();
    resetTransactionForm();
}

// Load wallets list
export async function loadAccounts() {
    const listContainer = document.getElementById('accounts-list-container');
    if (!listContainer) return;
    
    // Optimistic UI: Render cached wallets immediately to avoid visual lag or spinners on reload
    const cachedAccs = JSON.parse(localStorage.getItem('myWallets') || '[]');
    if (cachedAccs.length > 0) {
        state.accounts = cachedAccs;
        renderAccountsList();
        
        if (state.activeAccountUuid) {
            const exists = state.accounts.some(a => a.uuid === state.activeAccountUuid);
            if (exists) {
                selectAccount(state.activeAccountUuid);
            } else {
                state.activeAccountUuid = null;
                localStorage.removeItem('activeAccountUuid');
            }
        }
    } else {
        listContainer.innerHTML = `
            <div class="empty-state">
                <div class="spinner"></div>
                <p data-i18n="loadingWallets">${i18n[state.currentLang].loadingWallets}</p>
            </div>
        `;
    }

    try {
        // Fetch all wallets in a single request from the backend
        const fetchedAccounts = await apiRequest('/api/accounts');
        state.accounts = fetchedAccounts;

        localStorage.setItem('myWallets', JSON.stringify(state.accounts));
        
        // Handle deletion of active wallet
        if (state.activeAccountUuid && !state.accounts.some(a => a.uuid === state.activeAccountUuid)) {
            state.activeAccountUuid = null;
            localStorage.removeItem('activeAccountUuid');
            document.getElementById('statement-title').textContent = i18n[state.currentLang].ledgerHistoryTitle;
            document.getElementById('pdf-compile-btn').disabled = true;
            document.getElementById('statement-container').innerHTML = `
                <div class="empty-state">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p data-i18n="emptyHistoryPrompt">${i18n[state.currentLang].emptyHistoryPrompt}</p>
                </div>
            `;
        }

        renderAccountsList();
        
        // Refresh selected wallet statement in background
        if (state.activeAccountUuid) {
            selectAccount(state.activeAccountUuid);
        }
    } catch (err) {
        showToast(err.message, "error");
    }
}

// Render wallets grid
export function renderAccountsList() {
    const listContainer = document.getElementById('accounts-list-container');
    if (!listContainer) return;

    if (state.accounts.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-state">
                <p data-i18n="noWallets">${i18n[state.currentLang].noWallets}</p>
            </div>
        `;
        return;
    }

    // Get search and sort parameters
    const searchInput = document.getElementById('wallet-search');
    const sortSelect = document.getElementById('wallet-sort');
    const searchQuery = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const sortBy = sortSelect ? sortSelect.value : 'name-asc';

    // 1. Filter by name
    let displayedAccounts = [...state.accounts];
    if (searchQuery) {
        displayedAccounts = displayedAccounts.filter(acc => 
            acc.name.toLowerCase().includes(searchQuery)
        );
    }

    // 2. Sort by criteria
    displayedAccounts.sort((a, b) => {
        switch (sortBy) {
            case 'name-asc':
                return a.name.localeCompare(b.name);
            case 'name-desc':
                return b.name.localeCompare(a.name);
            case 'date-desc':
                return (b.id || 0) - (a.id || 0);
            case 'date-asc':
                return (a.id || 0) - (b.id || 0);
            case 'balance-asc':
                return parseFloat(a.balance) - parseFloat(b.balance);
            case 'balance-desc':
                return parseFloat(b.balance) - parseFloat(a.balance);
            case 'currency-asc':
                return a.currency.localeCompare(b.currency) || a.name.localeCompare(b.name);
            case 'currency-desc':
                return b.currency.localeCompare(a.currency) || a.name.localeCompare(b.name);
            default:
                return 0;
        }
    });

    if (displayedAccounts.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-state">
                <p>${state.currentLang === 'de' ? 'Keine Wallets gefunden' : 'No wallets found'}</p>
            </div>
        `;
        return;
    }

    listContainer.innerHTML = '';
    displayedAccounts.forEach(acc => {
        const card = document.createElement('div');
        card.className = `account-card ${state.activeAccountUuid === acc.uuid ? 'active' : ''}`;
        card.onclick = () => selectAccount(acc.uuid);

        const balanceStr = parseFloat(acc.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const currencySymbols = { USD: '$', EUR: '€', GBP: '£' };
        const symbol = currencySymbols[acc.currency] || '';

        card.innerHTML = `
            <div class="account-header">
                <span class="account-name">${escapeHtml(acc.name)}</span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="account-currency">${escapeHtml(acc.currency)}</span>
                    <button onclick="showEditAccountModal(decodeURIComponent('${encodeURIComponent(acc.uuid)}'), event)" class="btn-edit-acc" title="${i18n[state.currentLang].editWalletTitle}">✏️</button>
                </div>
            </div>
            <div class="account-meta" style="display: flex; align-items: center; gap: 6px;">
                <span>ID: ${escapeHtml(acc.customer_id)}</span>
                <button onclick="copyToClipboard(decodeURIComponent('${encodeURIComponent(acc.customer_id)}'), 'copiedCustomerId', event)" class="btn-copy" title="${i18n[state.currentLang].copyBtnTitle}">📋</button>
            </div>
            <div class="account-meta" style="font-family: monospace; font-size: 0.75rem; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                <span>UUID: ${escapeHtml(acc.uuid)}</span>
                <button onclick="copyToClipboard(decodeURIComponent('${encodeURIComponent(acc.uuid)}'), 'copiedUuid', event)" class="btn-copy" title="${i18n[state.currentLang].copyBtnTitle}">📋</button>
            </div>
            <div class="account-balance">${symbol} ${balanceStr} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">${escapeHtml(acc.currency)}</span></div>
        `;
        listContainer.appendChild(card);
    });

    updateTransactionDropdowns();
    if (typeof updateTestButtonsState === 'function') {
        updateTestButtonsState();
    }
}

export function handleWalletSearchAndSort() {
    renderAccountsList();
}

// Account modal toggles
export function showCreateAccountModal() {
    hideEditAccountModal();
    const createBox = document.getElementById('create-account-box');
    if (createBox) createBox.style.display = 'block';
    updateCreateAccountSuffix();
}

export function hideCreateAccountModal() {
    const createBox = document.getElementById('create-account-box');
    if (createBox) createBox.style.display = 'none';
    const form = document.getElementById('create-account-form');
    if (form) form.reset();
}

// Edit Account modal toggles
export function showEditAccountModal(uuid, event) {
    if (event) {
        event.stopPropagation();
    }
    
    hideCreateAccountModal();

    const acc = state.accounts.find(a => a.uuid === uuid);
    if (!acc) return;

    document.getElementById('edit-acc-uuid').value = uuid;
    document.getElementById('edit-acc-name').value = acc.name;
    document.getElementById('edit-acc-customer').value = acc.customer_id;
    document.getElementById('edit-acc-currency').value = acc.currency;

    const editBox = document.getElementById('edit-account-box');
    if (editBox) editBox.style.display = 'block';
}

export function hideEditAccountModal() {
    const editBox = document.getElementById('edit-account-box');
    if (editBox) editBox.style.display = 'none';
    const form = document.getElementById('edit-account-form');
    if (form) form.reset();
}

export async function handleEditAccount(event) {
    event.preventDefault();
    const uuid = document.getElementById('edit-acc-uuid').value;
    const name = document.getElementById('edit-acc-name').value;
    const customerId = document.getElementById('edit-acc-customer').value;
    const currency = document.getElementById('edit-acc-currency').value;

    try {
        const updatedAcc = await apiRequest(`/api/accounts/${uuid}`, 'PUT', {
            customer_id: customerId,
            name,
            currency
        });

        const idx = state.accounts.findIndex(a => a.uuid === uuid);
        if (idx !== -1) {
            state.accounts[idx] = updatedAcc;
        }
        localStorage.setItem('myWallets', JSON.stringify(state.accounts));
        
        showToast(i18n[state.currentLang].walletUpdatedSuccess, "success");
        hideEditAccountModal();
        renderAccountsList();

        if (state.activeAccountUuid === uuid) {
            document.getElementById('statement-title').textContent = `${i18n[state.currentLang].ledgerHistoryTitle}: ${updatedAcc.name}`;
        }
    } catch (err) {
        showToast(err.message, "error");
    }
}

// Create Account handler
export async function handleCreateAccount(event) {
    event.preventDefault();
    const name = document.getElementById('acc-name').value;
    const customerId = document.getElementById('acc-customer').value;
    const currency = document.getElementById('acc-currency').value;
    const initialBalance = document.getElementById('acc-balance').value || '0.0000';

    try {
        const newAcc = await apiRequest('/api/accounts', 'POST', {
            customer_id: customerId,
            name,
            currency,
            initial_balance: initialBalance
        });

        state.accounts.push(newAcc);
        localStorage.setItem('myWallets', JSON.stringify(state.accounts));
        
        showToast(i18n[state.currentLang].walletCreatedSuccess.replace('{name}', name), "success");
        hideCreateAccountModal();
        renderAccountsList();
    } catch (err) {
        showToast(err.message, "error");
    }
}

// Select Account and load transaction stream
export async function selectAccount(uuid) {
    state.activeAccountUuid = uuid;
    localStorage.setItem('activeAccountUuid', uuid);
    renderAccountsList();

    const selectedAcc = state.accounts.find(a => a.uuid === uuid);
    document.getElementById('statement-title').textContent = `${i18n[state.currentLang].ledgerHistoryTitle}: ${selectedAcc ? selectedAcc.name : ''}`;
    document.getElementById('pdf-compile-btn').disabled = false;

    const streamContainer = document.getElementById('statement-container');
    if (!streamContainer) return;
    streamContainer.innerHTML = `
        <div class="empty-state">
            <div class="spinner"></div>
            <p data-i18n="loadingHistory">${i18n[state.currentLang].loadingHistory}</p>
        </div>
    `;

    try {
        const entries = await apiRequest(`/api/accounts/${uuid}/statement`);
        if (entries.length === 0) {
            streamContainer.innerHTML = `
                <div class="empty-state">
                    <p data-i18n="emptyHistory">${i18n[state.currentLang].emptyHistory}</p>
                </div>
            `;
            return;
        }

        const selectedAcc = state.accounts.find(a => a.uuid === uuid);
        const currencySymbols = { USD: '$', EUR: '€', GBP: '£' };
        const symbol = selectedAcc ? (currencySymbols[selectedAcc.currency] || '') : '';
        const currencyCode = selectedAcc ? selectedAcc.currency : '';

        streamContainer.innerHTML = '';
        entries.forEach(entry => {
            const item = document.createElement('div');
            item.className = 'statement-item';

            const date = new Date(entry.created_at).toLocaleString();
            const directionLower = entry.direction.toLowerCase();
            const amountFormatted = parseFloat(entry.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            item.innerHTML = `
                <div class="statement-left">
                    <span class="statement-desc">${escapeHtml(entry.transaction.description)}</span>
                    <span class="statement-time">${date}</span>
                    <span class="statement-uuid">Tx UUID: ${escapeHtml(entry.transaction.uuid)}</span>
                </div>
                <div class="statement-right">
                    <span class="statement-amount ${directionLower}">
                        ${entry.direction === 'DEBIT' ? '-' : '+'}${symbol} ${amountFormatted}
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                        ${escapeHtml(entry.direction)} (${escapeHtml(currencyCode)})
                    </span>
                </div>
            `;
            streamContainer.appendChild(item);
        });
    } catch (err) {
        streamContainer.innerHTML = `
            <div class="empty-state">
                <p style="color: var(--danger-color)">${i18n[state.currentLang].errorHistory.replace('{error}', err.message)}</p>
            </div>
        `;
    }
}

// Asynchronous statement generation PDF triggers
export async function triggerStatementCompile() {
    if (!state.activeAccountUuid) return;
    const email = prompt(i18n[state.currentLang].enterPdfEmailPrompt, state.userEmail);
    if (!email) return;

    try {
        const response = await apiRequest(`/api/accounts/${state.activeAccountUuid}/statement/compile`, 'POST', { email });
        showToast(i18n[state.currentLang].toastQueuedPdf.replace('{msg}', response.message), "success");
    } catch (err) {
        showToast(err.message, "error");
    }
}

export function resetTransactionForm() {
    const form = document.getElementById('transaction-form');
    if (form) form.reset();
    const container = document.getElementById('entries-container');
    if (!container) return;
    const rows = container.querySelectorAll('.entry-row');
    rows.forEach(r => r.remove());
    
    state.entryRowsCount = 0;
    
    addEntryRow();
    addEntryRow();
}

export function updateCreateAccountSuffix() {
    const select = document.getElementById('acc-currency');
    const suffix = document.getElementById('create-acc-currency-suffix');
    const hint = document.getElementById('create-acc-balance-hint');
    if (select && suffix) {
        const val = select.value;
        suffix.textContent = val;
        if (hint) {
            if (state.currentLang === 'de') {
                hint.innerHTML = `Hinweis: Geben Sie einen Standardbetrag ein (z. B. <strong>10</strong> oder <strong>10,00</strong> für 10,00 ${val}). Wir unterstützen bis zu 4 Dezimalstellen für Präzisionsbuchungen (z. B. 10,0000 ${val}). Keine Tausendertrennzeichen verwenden.`;
            } else {
                hint.innerHTML = `Note: Enter a standard amount (e.g. <strong>10</strong> or <strong>10.00</strong> for 10.00 ${val}). We support up to 4 decimal places for precision entries (e.g. 10.0000 ${val}). Do not use thousands separators.`;
            }
        }
    }
}

export function onRowAccountChange(selectEl) {
    const row = selectEl.closest('.entry-row');
    if (!row) return;
    const accountUuid = selectEl.value;
    const account = state.accounts.find(a => a.uuid === accountUuid);
    const suffix = row.querySelector('.entry-amount-suffix');
    if (suffix) {
        suffix.textContent = account ? account.currency : '';
    }
    calculateDoubleEntryBalance();
}

export function addEntryRow() {
    const container = document.getElementById('entries-container');
    if (!container) return;
    const rowId = `entry-row-${state.entryRowsCount++}`;

    const row = document.createElement('div');
    row.className = 'entry-row';
    row.id = rowId;

    let selectOptions = `<option value="">${escapeHtml(i18n[state.currentLang].selectWalletPrompt)}</option>`;
    state.accounts.forEach(acc => {
        selectOptions += `<option value="${escapeHtml(acc.uuid)}">${escapeHtml(acc.name)} (${escapeHtml(acc.currency)})</option>`;
    });

    row.innerHTML = `
        <div>
            <select class="entry-account" required onchange="onRowAccountChange(this)">
                ${selectOptions}
            </select>
        </div>
        <div>
            <select class="entry-direction" required onchange="calculateDoubleEntryBalance()">
                <option value="DEBIT">${i18n[state.currentLang].debitOption}</option>
                <option value="CREDIT">${i18n[state.currentLang].creditOption}</option>
            </select>
        </div>
        <div class="input-with-suffix">
            <input type="number" class="entry-amount" step="any" placeholder="0.00" required oninput="calculateDoubleEntryBalance()" title="${i18n[state.currentLang].txAmountInputHint}">
            <span class="entry-amount-suffix input-suffix"></span>
        </div>
        <div>
            <button type="button" class="remove-btn" onclick="removeEntryRow('${rowId}')">✕</button>
        </div>
    `;

    container.appendChild(row);
    calculateDoubleEntryBalance();
}

export function removeEntryRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
        calculateDoubleEntryBalance();
    }
}

export function updateTransactionDropdowns() {
    const dropdowns = document.querySelectorAll('.entry-account');
    dropdowns.forEach(dropdown => {
        const selectedValue = dropdown.value;
        
        let selectOptions = `<option value="">${escapeHtml(i18n[state.currentLang].selectWalletPrompt)}</option>`;
        state.accounts.forEach(acc => {
            selectOptions += `<option value="${escapeHtml(acc.uuid)}">${escapeHtml(acc.name)} (${escapeHtml(acc.currency)})</option>`;
        });
        
        dropdown.innerHTML = selectOptions;
        dropdown.value = selectedValue;
        
        const row = dropdown.closest('.entry-row');
        if (row) {
            const account = state.accounts.find(a => a.uuid === selectedValue);
            const suffix = row.querySelector('.entry-amount-suffix');
            if (suffix) {
                suffix.textContent = account ? account.currency : '';
            }
        }
    });
}

// Live balance checker verifying Credits === Debits
export function calculateDoubleEntryBalance() {
    const container = document.getElementById('entries-container');
    if (!container) return;
    
    const rows = container.querySelectorAll('.entry-row');
    
    let totalDebits = 0;
    let totalCredits = 0;
    const selectedCurrencies = new Set();

    rows.forEach(row => {
        const accountUuid = row.querySelector('.entry-account').value;
        const direction = row.querySelector('.entry-direction').value;
        const amountVal = parseFloat(row.querySelector('.entry-amount').value) || 0;

        if (accountUuid) {
            const acc = state.accounts.find(a => a.uuid === accountUuid);
            if (acc) {
                selectedCurrencies.add(acc.currency);
            }
        }

        if (direction === 'DEBIT') {
            totalDebits += amountVal;
        } else if (direction === 'CREDIT') {
            totalCredits += amountVal;
        }
    });

    const sumDebitsEl = document.getElementById('sum-debits');
    const sumCreditsEl = document.getElementById('sum-credits');
    if (sumDebitsEl) sumDebitsEl.textContent = totalDebits.toFixed(2);
    if (sumCreditsEl) sumCreditsEl.textContent = totalCredits.toFixed(2);

    const badge = document.getElementById('balance-status-badge');
    const statusText = document.getElementById('balance-status-text');
    const postBtn = document.getElementById('post-tx-btn');
    const disabledReasonEl = document.getElementById('tx-disabled-reason');

    const diff = Math.abs(totalDebits - totalCredits);
    const isBalanced = rows.length >= 2 && diff < 0.01 && totalDebits > 0;
    const hasMultipleCurrencies = selectedCurrencies.size > 1;

    if (badge && statusText && postBtn && disabledReasonEl) {
        if (hasMultipleCurrencies) {
            badge.className = 'balance-status unbalanced';
            statusText.textContent = state.currentLang === 'de' ? "Währungsfehler" : "Currency Mismatch";
            postBtn.disabled = true;
            disabledReasonEl.style.display = 'block';
            disabledReasonEl.innerHTML = state.currentLang === 'de' 
                ? "Hinweis: Alle Buchungszeilen müssen dieselbe Währung verwenden (z. B. nur EUR oder nur USD)."
                : "Note: All entries must use the same currency (e.g. only EUR or only USD).";
        } else if (isBalanced) {
            badge.className = 'balance-status balanced';
            statusText.textContent = i18n[state.currentLang].balanced;
            postBtn.disabled = false;
            disabledReasonEl.style.display = 'none';
        } else {
            badge.className = 'balance-status unbalanced';
            statusText.textContent = totalDebits > 0 || totalCredits > 0 
                ? i18n[state.currentLang].unbalancedDiff.replace('{diff}', diff.toFixed(2)) 
                : i18n[state.currentLang].unbalanced;
            postBtn.disabled = true;
            disabledReasonEl.style.display = 'block';
            disabledReasonEl.textContent = i18n[state.currentLang].btnDisabledReason;
        }
    }
}

// Post Transaction Submit handler
export async function handlePostTransaction(event) {
    event.preventDefault();
    const description = document.getElementById('tx-desc').value;
    const container = document.getElementById('entries-container');
    if (!container) return;
    const rows = container.querySelectorAll('.entry-row');
    
    const entries = [];
    let isValid = true;

    rows.forEach(row => {
        const accountUuid = row.querySelector('.entry-account').value;
        const direction = row.querySelector('.entry-direction').value;
        const amount = row.querySelector('.entry-amount').value;

        if (!accountUuid || !direction || !amount) {
            isValid = false;
        }
        
        entries.push({
            account_uuid: accountUuid,
            direction,
            amount
        });
    });

    if (!isValid) {
        showToast("Please ensure all ledger rows are completed", "error");
        return;
    }

    const postBtn = document.getElementById('post-tx-btn');
    if (postBtn) {
        postBtn.disabled = true;
        postBtn.textContent = i18n[state.currentLang].postingBtn;
    }

    try {
        await apiRequest('/api/transactions', 'POST', {
            description,
            entries
        });

        showToast(i18n[state.currentLang].txPostedSuccess, "success");
        
        await loadAccounts();

        resetTransactionForm();
    } catch (err) {
        showToast(err.message, "error");
    } finally {
        if (postBtn) {
            postBtn.disabled = false;
            postBtn.textContent = i18n[state.currentLang].postTxBtn;
        }
    }
}

export function togglePasswordVisibility() {
    const pwdInput = document.getElementById('auth-password');
    const toggleBtn = document.querySelector('.password-toggle-btn');
    if (pwdInput && toggleBtn) {
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            toggleBtn.textContent = '🙈';
        } else {
            pwdInput.type = 'password';
            toggleBtn.textContent = '👁️';
        }
    }
}

// Bind all UI handler functions to the global window object
window.togglePasswordVisibility = togglePasswordVisibility;
window.setLanguage = setLanguage;
window.toggleAuthMode = toggleAuthMode;
window.handleAuthSubmit = handleAuthSubmit;
window.handleLogout = handleLogout;

window.showCreateAccountModal = showCreateAccountModal;
window.updateCreateAccountSuffix = updateCreateAccountSuffix;
window.handleCreateAccount = handleCreateAccount;
window.hideCreateAccountModal = hideCreateAccountModal;
window.handleEditAccount = handleEditAccount;
window.hideEditAccountModal = hideEditAccountModal;
window.handleWalletSearchAndSort = handleWalletSearchAndSort;
window.showEditAccountModal = showEditAccountModal;
window.copyToClipboard = copyToClipboard;
window.selectAccount = selectAccount;
window.triggerStatementCompile = triggerStatementCompile;
window.addEntryRow = addEntryRow;
window.removeEntryRow = removeEntryRow;
window.onRowAccountChange = onRowAccountChange;
window.calculateDoubleEntryBalance = calculateDoubleEntryBalance;
window.handlePostTransaction = handlePostTransaction;

// On Page Load: check if session token already cached
window.addEventListener('DOMContentLoaded', () => {
    const storedToken = localStorage.getItem('jwtToken');
    const storedRefresh = localStorage.getItem('refreshToken');
    const storedEmail = localStorage.getItem('userEmail');

    // Run translations initially (defaults to German)
    applyTranslations();

    if (storedToken && storedRefresh && storedEmail) {
        state.jwtToken = storedToken;
        state.refreshToken = storedRefresh;
        state.userEmail = storedEmail;
        initDashboard();
    } else {
        document.getElementById('auth-container').style.display = 'flex';
        document.getElementById('app-container').style.display = 'none';
        triggerLogoRepaint();
    }

    // Force repaint of text-gradient logos when fonts are fully loaded to prevent WebKit clipping repaint bugs
    if (document.fonts) {
        document.fonts.ready.then(() => {
            triggerLogoRepaint();
        });
    }
});
