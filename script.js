// ── Auth Form Switching ──
const loginForm    = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");

function showLogin() {
    loginForm.classList.remove("hidden");
    registerForm.classList.add("hidden");
}

function showRegister() {
    registerForm.classList.remove("hidden");
    loginForm.classList.add("hidden");
}

const showRegisterLink = document.getElementById("showRegisterForm");
const showLoginLink    = document.getElementById("showLoginForm");
if (showRegisterLink) showRegisterLink.addEventListener("click", (e) => { e.preventDefault(); showRegister(); });
if (showLoginLink)    showLoginLink.addEventListener("click", (e) => { e.preventDefault(); showLogin(); });
