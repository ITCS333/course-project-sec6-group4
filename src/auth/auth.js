function login() {
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    if (password === "password") {
        localStorage.setItem("user", email);
        alert("Login successful");
        document.getElementById("userInfo").innerText = "Logged in as: " + email;
    } else {
        alert("Wrong password");
    }
}

function logout() {
    localStorage.removeItem("user");
    document.getElementById("userInfo").innerText = "";
    alert("Logged out");
}