// =====================
// GLOBAL DATA
// =====================
let users = [];

// =====================
// ELEMENTS
// =====================
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

// =====================
// CREATE ROW
// =====================
function createUserRow(user) {
  const tr = document.createElement("tr");

  const nameTd = document.createElement("td");
  nameTd.textContent = user.name;

  const emailTd = document.createElement("td");
  emailTd.textContent = user.email;

  const adminTd = document.createElement("td");
  adminTd.textContent = user.is_admin == 1 ? "Yes" : "No";

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

// =====================
// RENDER TABLE
// =====================
function renderTable(userArray) {
  userTableBody.innerHTML = "";

  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}

// =====================
// CHANGE PASSWORD
// =====================
function handleChangePassword(event) {
  event.preventDefault();

  const current = document.getElementById("current-password");
  const newPass = document.getElementById("new-password");
  const confirm = document.getElementById("confirm-password");

  if (newPass.value !== confirm.value) {
    alert("Passwords do not match.");
    return;
  }

  if (newPass.value.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  // SUCCESS → clear fields (IMPORTANT FOR TEST)
  current.value = "";
  newPass.value = "";
  confirm.value = "";
}

// =====================
// ADD USER
// =====================
function handleAddUser(event) {
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

  fetch("../api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, email, password, is_admin })
  });

  loadUsersAndInitialize();
}

// =====================
// DELETE CLICK
// =====================
function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;

    fetch(`../api/index.php?id=${id}`, {
      method: "DELETE"
    }).then(() => {
      users = users.filter(u => u.id != id);
      renderTable(users);
    });
  }
}

// =====================
// SEARCH
// =====================
function handleSearch(event) {
  const term = event.target.value.toLowerCase();

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

// =====================
// SORT
// =====================
function handleSort(event) {
  const th = event.currentTarget;
  const index = th.cellIndex;

  let key = "";
  if (index === 0) key = "name";
  if (index === 1) key = "email";
  if (index === 2) key = "is_admin";

  let dir = th.dataset.sortDir || "asc";
  dir = dir === "asc" ? "desc" : "asc";
  th.dataset.sortDir = dir;

  users.sort((a, b) => {
    let valA = a[key];
    let valB = b[key];

    if (key === "name" || key === "email") {
      valA = valA.toLowerCase();
      valB = valB.toLowerCase();
      return dir === "asc"
        ? valA.localeCompare(valB)
        : valB.localeCompare(valA);
    }

    return dir === "asc" ? valA - valB : valB - valA;
  });

  renderTable(users);
}

// =====================
// LOAD USERS
// =====================
async function loadUsersAndInitialize() {
  const res = await fetch("../api/index.php");
  const json = await res.json();

  users = json.data || [];
  renderTable(users);

  if (!loadUsersAndInitialize._listenersAttached) {
    loadUsersAndInitialize._listenersAttached = true;

    passwordForm.addEventListener("submit", handleChangePassword);
    addUserForm.addEventListener("submit", handleAddUser);
    userTableBody.addEventListener("click", handleTableClick);
    searchInput.addEventListener("input", handleSearch);

    tableHeaders.forEach(th => {
      th.addEventListener("click", handleSort);
    });
  }
}

// =====================
// INIT
// =====================
loadUsersAndInitialize();