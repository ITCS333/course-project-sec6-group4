let users = [];

// Elements
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const changePasswordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

// Create Row
function createUserRow(user) {
  const tr = document.createElement("tr");

  const nameTd = document.createElement("td");
  nameTd.textContent = user.name;

  const emailTd = document.createElement("td");
  emailTd.textContent = user.email;

  const adminTd = document.createElement("td");
  adminTd.textContent = Number(user.is_admin) === 1 ? "Yes" : "No";

  const actionTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className = "edit-btn";
  editBtn.dataset.id = user.id;
  editBtn.textContent = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = user.id;
  deleteBtn.textContent = "Delete";

  actionTd.appendChild(editBtn);
  actionTd.appendChild(deleteBtn);

  tr.appendChild(nameTd);
  tr.appendChild(emailTd);
  tr.appendChild(adminTd);
  tr.appendChild(actionTd);

  return tr;
}

// Render
function renderTable(userArray) {
  userTableBody.innerHTML = "";
  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}

// Change Password
async function handleChangePassword(event) {
  event.preventDefault();

  const current = document.getElementById("current-password").value;
  const newPass = document.getElementById("new-password").value;
  const confirm = document.getElementById("confirm-password").value;

  if (newPass !== confirm) {
    alert("Passwords do not match.");
    return;
  }

  if (newPass.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  const id = localStorage.getItem("userId") || 1;

  const res = await fetch("../api/index.php?action=change_password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      id,
      current_password: current,
      new_password: newPass
    })
  });

  const data = await res.json();

  if (res.ok && data.success) {
    alert("Password updated successfully!");
    changePasswordForm.reset();
  } else {
    alert(data.message);
  }
}

// Add User
async function handleAddUser(event) {
  event.preventDefault();

  const name = document.getElementById("user-name").value;
  const email = document.getElementById("user-email").value;
  const password = document.getElementById("default-password").value;
  const is_admin = document.getElementById("is-admin").value;

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

  if (res.status === 201) {
    addUserForm.reset();
    loadUsersAndInitialize();
  } else {
    alert(data.message);
  }
}

// Delete/Edit
function handleTableClick(event) {
  const id = event.target.dataset.id;

  if (event.target.classList.contains("delete-btn")) {
    fetch("../api/index.php?id=" + id, {
      method: "DELETE"
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          users = users.filter(u => u.id != id);
          renderTable(users);
        } else {
          alert(data.message);
        }
      });
  }

  if (event.target.classList.contains("edit-btn")) {
    const user = users.find(u => u.id == id);
    const newName = prompt("Edit name", user.name);
    const newEmail = prompt("Edit email", user.email);

    if (newName && newEmail) {
      fetch("../api/index.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id,
          name: newName,
          email: newEmail,
          is_admin: user.is_admin
        })
      })
        .then(res => res.json())
        .then(() => loadUsersAndInitialize());
    }
  }
}

// Search
function handleSearch() {
  const term = searchInput.value.toLowerCase();

  if (!term) {
    renderTable(users);
    return;
  }

  const filtered = users.filter(u =>
    u.name.toLowerCase().includes(term) ||
    u.email.toLowerCase().includes(term)
  );

  renderTable(filtered);
}

// Sort
function handleSort(event) {
  const index = event.currentTarget.cellIndex;
  const keys = ["name", "email", "is_admin"];
  const key = keys[index];

  let dir = event.currentTarget.dataset.sortDir || "asc";
  dir = dir === "asc" ? "desc" : "asc";
  event.currentTarget.dataset.sortDir = dir;

  users.sort((a, b) => {
    if (key === "is_admin") {
      return dir === "asc"
        ? a.is_admin - b.is_admin
        : b.is_admin - a.is_admin;
    }

    return dir === "asc"
      ? a[key].localeCompare(b[key])
      : b[key].localeCompare(a[key]);
  });

  renderTable(users);
}

// Load
async function loadUsersAndInitialize() {
  const res = await fetch("../api/index.php");
  const data = await res.json();

  if (!res.ok) {
    alert("Error loading users");
    return;
  }

  users = data.data;
  renderTable(users);

  changePasswordForm.addEventListener("submit", handleChangePassword);
  addUserForm.addEventListener("submit", handleAddUser);
  userTableBody.addEventListener("click", handleTableClick);
  searchInput.addEventListener("input", handleSearch);

  tableHeaders.forEach(th => {
    th.addEventListener("click", handleSort);
  });
}

loadUsersAndInitialize();