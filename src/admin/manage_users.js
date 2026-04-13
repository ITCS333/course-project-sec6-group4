let users = [];

// --- Elements ---
let userTableBody = document.getElementById("user-table-body");
let addUserForm = document.getElementById("add-user-form");
let passwordForm = document.getElementById("password-form");
let searchInput = document.getElementById("search-input");
let tableHeaders = document.querySelectorAll("#user-table thead th");

// --- Create Row ---
function createUserRow(user) {
    let tr = document.createElement("tr");

    tr.innerHTML = `
        <td>${user.name}</td>
        <td>${user.email}</td>
        <td>${user.is_admin == 1 ? "Yes" : "No"}</td>
        <td>
            <button class="edit-btn" data-id="${user.id}">Edit</button>
            <button class="delete-btn" data-id="${user.id}">Delete</button>
        </td>
    `;

    return tr;
}

// --- Render Table ---
function renderTable(userArray) {
    userTableBody.innerHTML = "";

    userArray.forEach(user => {
        userTableBody.appendChild(createUserRow(user));
    });
}

// --- Change Password ---
async function handleChangePassword(event) {
    event.preventDefault();

    let current_password = document.getElementById("current-password").value;
    let new_password = document.getElementById("new-password").value;
    let confirm_password = document.getElementById("confirm-password").value;

    if (new_password !== confirm_password) {
        alert("Passwords do not match.");
        return;
    }

    if (new_password.length < 8) {
        alert("Password must be at least 8 characters.");
        return;
    }

    let response = await fetch("../api/index.php?action=change_password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id: 1,
            current_password,
            new_password
        })
    });

    let data = await response.json();

    if (response.ok) {
        alert("Password updated successfully!");
        passwordForm.reset();
    } else {
        alert(data.message || "Error");
    }
}

// --- Add User ---
async function handleAddUser(event) {
    event.preventDefault();

    let name = document.getElementById("user-name").value;
    let email = document.getElementById("user-email").value;
    let password = document.getElementById("default-password").value;
    let is_admin = document.getElementById("is-admin").value;

    if (!name || !email || !password) {
        alert("Please fill out all required fields.");
        return;
    }

    if (password.length < 8) {
        alert("Password must be at least 8 characters.");
        return;
    }

    let response = await fetch("../api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password, is_admin })
    });

    let data = await response.json();

    if (response.ok) {
        alert("User added successfully!");
        addUserForm.reset();
        loadUsersAndInitialize();
    } else {
        alert(data.message || "Error");
    }
}

// --- Delete / Edit ---
async function handleTableClick(event) {
    let id = event.target.dataset.id;

    if (event.target.classList.contains("delete-btn")) {
        let response = await fetch("../api/index.php?id=" + id, {
            method: "DELETE"
        });

        let data = await response.json();

        if (response.ok) {
            users = users.filter(u => u.id != id);
            renderTable(users);
        } else {
            alert(data.message || "Delete failed");
        }
    }

    if (event.target.classList.contains("edit-btn")) {
        let user = users.find(u => u.id == id);

        if (user) {
            let newName = prompt("Edit name:", user.name);
            let newEmail = prompt("Edit email:", user.email);

            if (newName && newEmail) {
                await fetch("../api/index.php", {
                    method: "PUT",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        id,
                        name: newName,
                        email: newEmail,
                        is_admin: user.is_admin
                    })
                });

                loadUsersAndInitialize();
            }
        }
    }
}

// --- Search ---
function handleSearch() {
    let term = searchInput.value.toLowerCase();

    if (!term) {
        renderTable(users);
        return;
    }

    let filtered = users.filter(u =>
        u.name.toLowerCase().includes(term) ||
        u.email.toLowerCase().includes(term)
    );

    renderTable(filtered);
}

// --- Sort ---
function handleSort(event) {
    let index = event.currentTarget.cellIndex;

    let key = "";
    if (index === 0) key = "name";
    if (index === 1) key = "email";
    if (index === 2) key = "is_admin";

    let dir = event.currentTarget.dataset.sortDir || "asc";
    dir = dir === "asc" ? "desc" : "asc";
    event.currentTarget.dataset.sortDir = dir;

    users.sort((a, b) => {
        if (key === "is_admin") {
            return dir === "asc" ? a[key] - b[key] : b[key] - a[key];
        } else {
            return dir === "asc"
                ? a[key].localeCompare(b[key])
                : b[key].localeCompare(a[key]);
        }
    });

    renderTable(users);
}

// --- Load + Init ---
async function loadUsersAndInitialize() {
    let response = await fetch("../api/index.php");

    if (!response.ok) {
        alert("Failed to load users");
        return;
    }

    let data = await response.json();

    users = data.data || [];
    renderTable(users);

    if (!window.initialized) {
        window.initialized = true;

        addUserForm.addEventListener("submit", handleAddUser);
        passwordForm.addEventListener("submit", handleChangePassword);
        userTableBody.addEventListener("click", handleTableClick);
        searchInput.addEventListener("input", handleSearch);

        tableHeaders.forEach(th => {
            th.addEventListener("click", handleSort);
        });
    }
}

loadUsersAndInitialize();