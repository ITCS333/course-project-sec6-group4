let users = [];

// Element selections
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

// ---------------- CREATE ROW ----------------
function createUserRow(user) {
  const tr = document.createElement("tr");

  const nameTd = document.createElement("td");
  nameTd.textContent = user.name;

  const emailTd = document.createElement("td");
  emailTd.textContent = user.email;

  const adminTd = document.createElement("td");
  adminTd.textContent = user.is_admin === 1 ? "Yes" : "No";

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className = "edit-btn";
  editBtn.dataset.id = user.id;
  editBtn.textContent = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = user.id;
  deleteBtn.textContent = "Delete";

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(nameTd);
  tr.appendChild(emailTd);
  tr.appendChild(adminTd);
  tr.appendChild(actionsTd);

  return tr;
}

// ---------------- RENDER TABLE ----------------
function renderTable(userArray) {
  userTableBody.innerHTML = "";

  for (let i = 0; i < userArray.length; i++) {
    userTableBody.appendChild(createUserRow(userArray[i]));
  }
}

// ---------------- CHANGE PASSWORD ----------------
async function handleChangePassword(event) {
  event.preventDefault();

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

  const id = 1;

  const res = await fetch("../api/index.php?action=change_password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      id,
      current_password,
      new_password
    })
  });

  const data = await res.json();

  if (res.ok) {
    alert("Password updated successfully!");
    passwordForm.reset();
  } else {
    alert(data.message);
  }
}

// ---------------- ADD USER ----------------
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
    body: JSON.stringify({
      name,
      email,
      password,
      is_admin
    })
  });

  if (res.status === 201) {
    await loadUsersAndInitialize();
    addUserForm.reset();
  } else {
    const data = await res.json();
    alert(data.message);
  }
}

// ---------------- TABLE CLICK ----------------
async function handleTableClick(event) {
  const target = event.target;
  const id = target.dataset.id;

  if (target.classList.contains("delete-btn")) {
    const res = await fetch("../api/index.php?id=" + id, {
      method: "DELETE"
    });

    const data = await res.json();

    if (res.ok) {
      users = users.filter(u => u.id != id);
      renderTable(users);
    } else {
      alert(data.message);
    }
  }

  if (target.classList.contains("edit-btn")) {
    const name = prompt("Enter new name");
    const email = prompt("Enter new email");

    const res = await fetch("../api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id,
        name,
        email
      })
    });

    const data = await res.json();

    if (res.ok) {
      await loadUsersAndInitialize();
    } else {
      alert(data.message);
    }
  }
}

// ---------------- SEARCH ----------------
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

// ---------------- SORT ----------------
function handleSort(event) {
  const index = event.currentTarget.cellIndex;

  let key = "";
  if (index === 0) key = "name";
  if (index === 1) key = "email";
  if (index === 2) key = "is_admin";

  let dir = event.currentTarget.dataset.sortDir || "asc";
  dir = dir === "asc" ? "desc" : "asc";
  event.currentTarget.dataset.sortDir = dir;

  users.sort((a, b) => {
    let res = 0;

    if (key === "is_admin") {
      res = Number(a[key]) - Number(b[key]);
    } else {
      res = a[key].localeCompare(b[key]);
    }

    return dir === "asc" ? res : -res;
  });

  renderTable(users);
}

// ---------------- LOAD ----------------
async function loadUsersAndInitialize() {
  const res = await fetch("../api/index.php");
  const data = await res.json();

  if (!res.ok) {
    alert("Error loading users");
    return;
  }

  users = data.data;
  renderTable(users);

  addUserForm.addEventListener("submit", handleAddUser);
  passwordForm.addEventListener("submit", handleChangePassword);
  userTableBody.addEventListener("click", handleTableClick);
  searchInput.addEventListener("input", handleSearch);

  tableHeaders.forEach(th => {
    th.addEventListener("click", handleSort);
  });
}

loadUsersAndInitialize();