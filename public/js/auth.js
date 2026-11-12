const loginForm = document.getElementById("login-form");
const registerForm = document.getElementById("register-form");

const title = document.getElementById("form-title");
const subtitle = document.getElementById("form-subtitle");

const switchText = document.getElementById("switch-text");
const switchButton = document.getElementById("switch-btn");

const message = document.getElementById("form-message");

let currentMode = document.body.dataset.mode || "login";