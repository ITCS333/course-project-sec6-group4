let users = [];

const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

const currentUser = JSON.parse(localStorage.getItem("user")) || {};

// ================= ROW =================
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

// ================= RENDER =================
function renderTable(arr) {
    userTableBody.innerHTML = "";
    arr.forEach(u => userTableBody.appendChild(createUserRow(u)));
}

// ================= PASSWORD =================
async function handleChangePassword(e) {
    e.preventDefault();

    const current_password = document.getElementById("current-password").value;
    const new_password = document.getElementById("new-password").value;
    const confirm_password = document.getElementById("confirm-password").value;

    if (new_password !== confirm_password) {
        alert("Passwords do not match.");
        return;
    }

    if (new_password.length < 8) {
        alert("Password must be at least 8 characters.");
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

    if (!data.success) {
        alert(data.message);
        return;
    }

    alert("Password updated successfully!");
    passwordForm.reset();
}

// ================= ADD USER =================
async function handleAddUser(e) {
    e.preventDefault();

    const name = document.getElementById("user-name").value;
    const email = document.getElementById("user-email").value;
    const password = document.getElementById("default-password").value;
    const is_admin = document.getElementById("is-admin").value;

    if (!name || !email || !password) {
        alert("Please fill all fields");
        return;
    }

    if (password.length < 8) {
        alert("Password must be at least 8 characters");
        return;
    }

    const res = await fetch("../api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password, is_admin })
    });

    const data = await res.json();

    if (res.status === 201) {
        addUserForm.reset();
        loadUsersAndInitialize();
    } else {
        alert(data.message);
    }
}

// ================= DELETE / EDIT =================
async function handleTableClick(e) {

    const id = e.target.dataset.id;

    if (e.target.classList.contains("delete-btn")) {

        const res = await fetch("../api/index.php?id=" + id, {
            method: "DELETE"
        });

        const data = await res.json();

        if (data.success) {
            users = users.filter(u => u.id != id);
            renderTable(users);
        }
    }

    if (e.target.classList.contains("edit-btn")) {

        const name = prompt("Name:");
        const email = prompt("Email:");

        const res = await fetch("../api/index.php", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, name, email })
        });

        const data = await res.json();

        if (data.success) {
            loadUsersAndInitialize();
        }
    }
}

// ================= SEARCH =================
function handleSearch() {
    const term = searchInput.value.toLowerCase();

    const filtered = users.filter(u =>
        u.name.toLowerCase().includes(term) ||
        u.email.toLowerCase().includes(term)
    );

    renderTable(filtered);
}

// ================= SORT =================
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

// ================= LOAD =================
async function loadUsersAndInitialize() {

    const res = await fetch("../api/index.php");
    const data = await res.json();

    if (!data.success) {
        alert("Error loading users");
        return;
    }

    users = data.data;
    renderTable(users);

    if (!addUserForm.dataset.bound) {

        addUserForm.addEventListener("submit", handleAddUser);
        passwordForm.addEventListener("submit", handleChangePassword);
        userTableBody.addEventListener("click", handleTableClick);
        searchInput.addEventListener("input", handleSearch);

        tableHeaders.forEach(th => th.addEventListener("click", handleSort));

        addUserForm.dataset.bound = "true";
    }
}

loadUsersAndInitialize();