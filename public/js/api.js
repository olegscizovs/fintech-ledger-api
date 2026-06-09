import { state } from './state.js';
import { i18n } from './i18n.js';

// Base client utility for API calls
export async function apiRequest(url, method = 'GET', body = null, requireAuth = true) {
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (requireAuth && state.jwtToken) {
        headers['Authorization'] = `Bearer ${state.jwtToken}`;
    }

    const options = {
        method,
        headers
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    let response = await fetch(url, options);

    // Handle token expiry and automatic rotation
    if (response.status === 401 && requireAuth && state.refreshToken) {
        console.log("JWT expired, attempting auto token rotation...");
        const rotated = await rotateTokens();
        if (rotated) {
            // Retry original request with new token
            headers['Authorization'] = `Bearer ${state.jwtToken}`;
            response = await fetch(url, options);
        } else {
            const { showToast, handleLogout } = await import('./app.js');
            showToast(i18n[state.currentLang].sessionExpired, "error");
            handleLogout();
            throw new Error("Unauthorized");
        }
    }

    const data = await response.json().catch(() => ({}));
    
    if (!response.ok) {
        const errorMsg = data.error || data.message || `API error (${response.status})`;
        throw new Error(errorMsg);
    }

    return data;
}

// Token rotation helper
export async function rotateTokens() {
    try {
        const response = await fetch('/api/refresh', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: state.refreshToken })
        });

        if (response.ok) {
            const data = await response.json();
            state.jwtToken = data.token;
            state.refreshToken = data.refresh_token;
            localStorage.setItem('jwtToken', state.jwtToken);
            localStorage.setItem('refreshToken', state.refreshToken);
            
            const tokenSpan = document.querySelector('.token-status span');
            if (tokenSpan) {
                tokenSpan.textContent = i18n[state.currentLang].sessionRotated;
            }
            return true;
        }
    } catch (err) {
        console.error("Token rotation failed:", err);
    }
    return false;
}
