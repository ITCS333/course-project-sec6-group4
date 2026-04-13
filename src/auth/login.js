let loginForm = document.getElementById("login-form");
let messageContainer = document.getElementById("message-container");

function showMessage(message, type) {
    messageContainer.textContent = message;
    messageContainer.className = type;
}

loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();

    try {
        let response = await fetch("api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ email, password })
        });

        let data = await response.json();

        if (data.success) {
            showMessage("Login successful!", "success");

            localStorage.setItem("user", JSON.stringify(data.user));
        } else {
            showMessage(data.message, "error");
        }

    } catch (error) {
        showMessage("Server error", "error");
    }
});