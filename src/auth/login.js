const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

// ---------------- DISPLAY MESSAGE ----------------
function displayMessage(message, type) {
    messageContainer.textContent = message;
    messageContainer.className = type;
}

// ---------------- EMAIL VALIDATION ----------------
function isValidEmail(email) {
    return /\S+@\S+\.\S+/.test(email);
}

// ---------------- PASSWORD VALIDATION ----------------
function isValidPassword(password) {
    return password.length >= 8;
}

// ---------------- HANDLE LOGIN ----------------
function handleLogin(event) {
    event.preventDefault();

    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!isValidEmail(email)) {
        displayMessage("Invalid email format.", "error");
        return;
    }

    if (!isValidPassword(password)) {
        displayMessage("Password must be at least 8 characters.", "error");
        return;
    }

    displayMessage("Login successful!", "success");

    emailInput.value = "";
    passwordInput.value = "";
}

// ---------------- SETUP ----------------
function setupLoginForm() {
    if (loginForm) {
        loginForm.addEventListener("submit", handleLogin);
    }
}

// ---------------- INIT ----------------
setupLoginForm();