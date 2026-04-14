let users = [];


const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");


if (!userTableBody || !addUserForm || !searchInput) {
    console.error("Missing HTML elements");
}

//  
const currentUser = JSON.parse(localStorage.getItem("user")) || null;

// ---------------- CREATE ROW ----------------
function createUserRow(user) {
    const tr = document.createElement("tr");

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

// ---------------- RENDER ----------------
function renderTable(userArray) {
    userTableBody.innerHTML = "";
    userArray.forEach(u => userTableBody.appendChild(createUserRow(u)));
}

// ---------------- CHANGE PASSWORD ----------------
async function handleChangePassword(e) {
    e.preventDefault();

    const current_password = document.getElementById("current-password")?.value;
    const new_password = document.getElementById("new-password")?.value;
    const confirm_password = document.getElementById("confirm-password")?.value;

    if (!current_password || !new_password || !confirm_password) {
        alert("Fill all fields");
        return;
    }

    if (new_password !== confirm_password) {
        alert("Passwords do not match");
        return;
    }

    if (new_password.length < 8) {
        alert("Password must be at least 8 characters");
        return;
    }

    if (!currentUser) {
        alert("Not logged in");
        return;
    }

    const res = await fetch("../api/index.php?action=change_password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id: currentUser.id,
            current_password,
            new_password
        })
    });

    const data = await res.json();

    if (data.success) {
        alert("Password updated successfully!");
        passwordForm?.reset();
    } else {
        alert(data.message);
    }
}

// ---------------- ADD USER ----------------
async function handleAddUser(e) {
    e.preventDefault();

    const name = document.getElementById("user-name")?.value.trim();
    const email = document.getElementById("user-email")?.value.trim();
    const password = document.getElementById("default-password")?.value;
    const is_admin = document.getElementById("is-admin")?.value;

    if (!name || !email || !password) {
        alert("Please fill out all required fields.");
        return;
    }

    if (password.length < 8) {
        alert("Password must be at least 8 characters.");
        return;
    }

    const res = await fetch("../api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password, is_admin })
    });

    const data = await res.json();

    if (data.success) {
        alert("User added successfully!");
        addUserForm.reset();
        loadUsersAndInitialize();
    } else {
        alert(data.message);
    }
}

// ---------------- DELETE / EDIT ----------------
async function handleTableClick(e) {
    const btn = e.target;
    const id = btn.dataset.id;

    if (!id) return;

    if (btn.classList.contains("delete-btn")) {

        const res = await fetch("../api/index.php?id=" + id, {
            method: "DELETE"
        });

        const data = await res.json();

        if (data.success) {
            users = users.filter(u => u.id != id);
            renderTable(users);
        } else {
            alert(data.message);
        }
    }

    if (btn.classList.contains("edit-btn")) {

        const name = prompt("New name?");
        const email = prompt("New email?");

        if (!name || !email) return;

        const res = await fetch("../api/index.php", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, name, email })
        });

        const data = await res.json();

        if (data.success) {
            loadUsersAndInitialize();
        } else {
            alert(data.message);
        }
    }
}

// ---------------- SEARCH ----------------
function handleSearch() {
    const term = searchInput.value.toLowerCase();

    if (!term) return renderTable(users);

    const filtered = users.filter(u =>
        u.name.toLowerCase().includes(term) ||
        u.email.toLowerCase().includes(term)
    );

    renderTable(filtered);
}

// ---------------- SORT ----------------
function handleSort(e) {
    const index = e.currentTarget.cellIndex;
    const keys = ["name", "email", "is_admin"];
    const key = keys[index];

    let dir = e.currentTarget.dataset.sortDir || "asc";
    dir = dir === "asc" ? "desc" : "asc";
    e.currentTarget.dataset.sortDir = dir;

    users.sort((a, b) => {
        if (key === "is_admin") {
            return dir === "asc" ? a[key] - b[key] : b[key] - a[key];
        }
        return dir === "asc"
            ? a[key].localeCompare(b[key])
            : b[key].localeCompare(a[key]);
    });

    renderTable(users);
}

// ---------------- LOAD ----------------
async function loadUsersAndInitialize() {

    const res = await fetch("../api/index.php");

    if (!res.ok) {
        alert("Error loading users");
        return;
    }

    const data = await res.json();
    users = data.data;

    renderTable(users);

    if (!addUserForm.dataset.bound) {

        addUserForm.addEventListener("submit", handleAddUser);

        if (passwordForm) {
            passwordForm.addEventListener("submit", handleChangePassword);
        }

        userTableBody.addEventListener("click", handleTableClick);
        searchInput.addEventListener("input", handleSearch);

        tableHeaders.forEach(th => {
            th.addEventListener("click", handleSort);
        });

        addUserForm.dataset.bound = "true";
    }
}

loadUsersAndInitialize();