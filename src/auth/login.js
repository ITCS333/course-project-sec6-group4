const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

function displayMessage(message, type) {
    messageContainer.textContent = message;
    messageContainer.className = type;
}

function isValidEmail(email) {
    return /\S+@\S+\.\S+/.test(email);
}

function isValidPassword(password) {
    return password.length >= 8;
}

async function handleLogin(event) {
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

    const res = await fetch("../api/login.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ email, password })
    });

    const data = await res.json();

    if (data.success) {

        displayMessage("Login successful!", "success");

        // 
        localStorage.setItem("user", JSON.stringify(data.user));

        emailInput.value = "";
        passwordInput.value = "";

        //  ( 

    } else {
        displayMessage(data.message, "error");
    }
}

function setupLoginForm() {
    if (loginForm) {
        loginForm.addEventListener("submit", handleLogin);
    }
}

setupLoginForm();