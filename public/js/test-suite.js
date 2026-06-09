import { state } from './state.js';
import { apiRequest } from './api.js';
import { i18n } from './i18n.js';
import { showToast, loadAccounts } from './app.js';

export async function createTestWallets() {
    const addBtn = document.getElementById('btn-add-test-wallets');
    const deleteBtn = document.getElementById('btn-delete-test-wallets');
    if (!addBtn || addBtn.disabled) return;

    addBtn.disabled = true;
    addBtn.textContent = state.currentLang === 'de' ? 'Erstelle...' : 'Creating...';

    const testWalletsData = [
        { name: "Liam Smith", customer_id: "test_liq_01", currency: "EUR", initial_balance: "5000.00" },
        { name: "Noah Jones", customer_id: "test_liq_02", currency: "USD", initial_balance: "1200.50" },
        { name: "Oliver Miller", customer_id: "test_liq_03", currency: "GBP", initial_balance: "350.00" },
        { name: "Elijah Davis", customer_id: "test_liq_04", currency: "EUR", initial_balance: "0.00" },
        { name: "James Rodriguez", customer_id: "test_liq_05", currency: "USD", initial_balance: "9800.75" },
        { name: "William Martinez", customer_id: "test_liq_06", currency: "EUR", initial_balance: "120.00" },
        { name: "Benjamin Hernandez", customer_id: "test_liq_07", currency: "GBP", initial_balance: "4500.00" },
        { name: "Lucas Lopez", customer_id: "test_liq_08", currency: "USD", initial_balance: "850.50" },
        { name: "Henry Gonzalez", customer_id: "test_liq_09", currency: "EUR", initial_balance: "2300.00" },
        { name: "Alexander Wilson", customer_id: "test_liq_10", currency: "USD", initial_balance: "15.00" },
        { name: "Mia Anderson", customer_id: "test_liq_11", currency: "EUR", initial_balance: "7500.00" },
        { name: "Emma Thomas", customer_id: "test_liq_12", currency: "USD", initial_balance: "3200.40" },
        { name: "Olivia Taylor", customer_id: "test_liq_13", currency: "GBP", initial_balance: "60.00" },
        { name: "Sophia Moore", customer_id: "test_liq_14", currency: "USD", initial_balance: "0.00" },
        { name: "Amelia Jackson", customer_id: "test_liq_15", currency: "EUR", initial_balance: "490.99" },
        { name: "Isabella Martin", customer_id: "test_liq_16", currency: "USD", initial_balance: "1850.00" },
        { name: "Charlotte Lee", customer_id: "test_liq_17", currency: "GBP", initial_balance: "300.00" },
        { name: "Harper Perez", customer_id: "test_liq_18", currency: "EUR", initial_balance: "95.50" },
        { name: "Evelyn Thompson", customer_id: "test_liq_19", currency: "USD", initial_balance: "6100.00" },
        { name: "Abigail White", customer_id: "test_liq_20", currency: "EUR", initial_balance: "420.00" },
        { name: "Emily Harris", customer_id: "test_liq_21", currency: "GBP", initial_balance: "8800.00" }
    ];

    const uuids = [];
    const newAccounts = [];
    try {
        for (const data of testWalletsData) {
            const response = await fetch('/api/accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${state.jwtToken}`
                },
                body: JSON.stringify(data)
            });
            if (response.ok) {
                const resData = await response.json();
                uuids.push(resData.uuid);
                newAccounts.push(resData);
            }
        }

        if (uuids.length > 0) {
            localStorage.setItem('createdTestWalletUuids', JSON.stringify(uuids));
            state.accounts.push(...newAccounts);
            localStorage.setItem('myWallets', JSON.stringify(state.accounts));
            
            showToast(
                state.currentLang === 'de' ? `${uuids.length} Test-Wallets erfolgreich erstellt!` : `${uuids.length} test wallets created successfully!`,
                'success'
            );
        } else {
            throw new Error("Failed to create any test wallets");
        }
    } catch (err) {
        showToast(err.message, 'error');
        addBtn.disabled = false;
        addBtn.textContent = i18n[state.currentLang].addTestWallets || "Test Wallets";
        return;
    }

    addBtn.textContent = i18n[state.currentLang].addTestWallets || "Test Wallets";
    updateTestButtonsState();
    await loadAccounts();
}

export async function deleteTestWallets() {
    const deleteBtn = document.getElementById('btn-delete-test-wallets');
    if (!deleteBtn || deleteBtn.disabled) return;

    deleteBtn.disabled = true;
    deleteBtn.textContent = state.currentLang === 'de' ? 'Lösche...' : 'Deleting...';

    const testWallets = state.accounts.filter(acc => acc.customer_id && acc.customer_id.startsWith('test_liq_'));
    const uuids = testWallets.map(acc => acc.uuid);
    let deletedCount = 0;

    try {
        for (const uuid of uuids) {
            const response = await fetch(`/api/accounts/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${state.jwtToken}`
                }
            });
            if (response.ok) {
                deletedCount++;
                state.accounts = state.accounts.filter(a => a.uuid !== uuid);
            }
        }

        localStorage.setItem('myWallets', JSON.stringify(state.accounts));

        showToast(
            state.currentLang === 'de' ? `${deletedCount} Test-Wallets gelöscht!` : `${deletedCount} test wallets deleted!`,
            'info'
        );
    } catch (err) {
        showToast(err.message, 'error');
    }

    localStorage.removeItem('createdTestWalletUuids');
    deleteBtn.textContent = i18n[state.currentLang].deleteTestWallets || "Delete Test Wallets";
    updateTestButtonsState();
    await loadAccounts();
}

export function updateTestButtonsState() {
    const addBtn = document.getElementById('btn-add-test-wallets');
    const deleteBtn = document.getElementById('btn-delete-test-wallets');
    if (!addBtn || !deleteBtn) return;

    const testWallets = state.accounts.filter(acc => acc.customer_id && acc.customer_id.startsWith('test_liq_'));
    if (testWallets.length > 0) {
        addBtn.disabled = true;
        deleteBtn.disabled = false;
    } else {
        addBtn.disabled = false;
        deleteBtn.disabled = true;
    }
}

// Expose to window for inline HTML handlers
window.createTestWallets = createTestWallets;
window.deleteTestWallets = deleteTestWallets;
