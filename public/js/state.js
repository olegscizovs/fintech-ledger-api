// State management variables for VeloLedger application
export const state = {
    currentLang: localStorage.getItem('appLanguage') || 'de',
    authMode: 'login', // 'login' or 'register'
    jwtToken: '',
    refreshToken: '',
    userEmail: '',
    accounts: [],
    activeAccountUuid: null,
    entryRowsCount: 0
};
