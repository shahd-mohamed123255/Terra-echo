document.addEventListener('DOMContentLoaded', () => {
    const loginForm    = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const title        = document.getElementById('form-title');
    const subtitle     = document.getElementById('form-subtitle');
    const switchText   = document.getElementById('switch-text');

    function setMode(mode) {
        const isLogin = mode === 'login';

        loginForm.style.display    = isLogin ? '' : 'none';
        registerForm.style.display = isLogin ? 'none' : '';

        title.textContent    = isLogin ? 'Welcome Back' : 'Create Account';
        subtitle.textContent = isLogin
            ? 'Sign in to continue your journey'
            : 'Join us and explore your farm';

        switchText.innerHTML = (isLogin
            ? "Don't have an account? "
            : 'Already have an account? ')
            + '<button type="button" id="switch-btn">'
            + (isLogin ? 'Sign up' : 'Login')
            + '</button>';

        document.body.dataset.mode = mode;
        history.replaceState(null, '', isLogin ? '/login' : '/register');
    }

    switchText.addEventListener('click', (e) => {
        if (e.target.id === 'switch-btn') {
            setMode(document.body.dataset.mode === 'login' ? 'register' : 'login');
        }
    });
});