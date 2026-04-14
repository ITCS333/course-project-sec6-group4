let users = [];


const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");


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


function renderTable(userArray) {
  userTableBody.innerHTML = "";
  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}


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

  const id =
    (typeof localStorage !== "undefined" && localStorage.getItem("userId")) || 1;

  const res = await fetch("../api/index.php?action=change_password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id, current_password, new_password })
  });

  const data = await res.json();

  if (res.ok) {
    alert("Password updated successfully!");
    passwordForm.reset();
  } else {
    alert(data.message);
  }
}


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

  if (res.status === 201) {
    await loadUsersAndInitialize();
    addUserForm.reset();
  } else {
    const data = await res.json();
    alert(data.message);
  }
}


async function handleTableClick(event) {
  const id = event.target.dataset.id;

  if (event.target.classList.contains("delete-btn")) {
    const res = await fetch(`../api/index.php?id=${id}`, {
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

  if (event.target.classList.contains("edit-btn")) {
    const newName = prompt("Enter new name:");
    const newEmail = prompt("Enter new email:");

    if (!newName || !newEmail) return;

    const res = await fetch("../api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id,
        name: newName,
        email: newEmail
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


function handleSearch() {
  const term = searchInput.value.toLowerCase();

  if (!term) return renderTable(users);

  const filtered = users.filter(
    u =>
      u.name.toLowerCase().includes(term) ||
      u.email.toLowerCase().includes(term)
  );

  renderTable(filtered);
}


function handleSort(event) {
  const index = event.currentTarget.cellIndex;

  const map = ["name", "email", "is_admin"];
  const key = map[index];

  let dir = event.currentTarget.dataset.sortDir || "asc";
  dir = dir === "asc" ? "desc" : "asc";
  event.currentTarget.dataset.sortDir = dir;

  users.sort((a, b) => {
    let res =
      key === "is_admin"
        ? Number(a[key]) - Number(b[key])
        : a[key].localeCompare(b[key]);

    return dir === "asc" ? res : -res;
  });

  renderTable(users);
}


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

  tableHeaders.forEach(th =>
    th.addEventListener("click", handleSort)
  );
}

loadUsersAndInitialize();