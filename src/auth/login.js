let loginForm = document.getElementById("login-form");
let emailInput = document.getElementById("email");
let passwordInput = document.getElementById("password");
let messageContainer = document.getElementById("message-container");

// --- Display Message ---
function displayMessage(message, type) {
    messageContainer.textContent = message;
    messageContainer.className = type;
}

// --- Email Validation ---
function isValidEmail(email) {
    return /\S+@\S+\.\S+/.test(email);
}

// --- Password Validation ---
function isValidPassword(password) {
    return password.length >= 8;
}

// --- Handle Login ---
function handleLogin(event) {
    event.preventDefault();

    let email = emailInput.value.trim();
    let password = passwordInput.value.trim();

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

// --- Setup Form ---
function setupLoginForm() {
    if (loginForm) {
        loginForm.addEventListener("submit", handleLogin);
    }
}

// --- Start ---
setupLoginForm();