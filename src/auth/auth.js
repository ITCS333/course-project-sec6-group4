// ===== LOGIN =====
async function login() {

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();

    if (!email || !password) {
        alert("Please fill all fields");
        return;
    }

    try {
        let response = await fetch("api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email,
                password
            })
        });

        let data = await response.json();

        if (data.success) {

            // save user in localStorage
            localStorage.setItem("user", JSON.stringify(data.user));

            alert("Login successful");

            // show user info if element exists
            const userInfo = document.getElementById("userInfo");
            if (userInfo) {
                userInfo.innerText = "Logged in as: " + data.user.email;
            }

        } else {
            alert(data.message || "Login failed");
        }

    } catch (error) {
        alert("Server error");
    }
}


// ===== LOGOUT =====
function logout() {

    localStorage.removeItem("user");

    const userInfo = document.getElementById("userInfo");
    if (userInfo) {
        userInfo.innerText = "";
    }

    alert("Logged out");
}