let users = [];

const tableBody = document.getElementById("user-table-body");
const form = document.getElementById("add-user-form");

function render() {
    tableBody.innerHTML = "";

    users.forEach(u => {
        let row = document.createElement("tr");
        row.innerHTML = `
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>${u.is_admin}</td>
        `;
        tableBody.appendChild(row);
    });
}

async function loadUsers() {
    let res = await fetch("api/index.php");
    let data = await res.json();
    users = data.data;
    render();
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();

    await fetch("api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            name: document.getElementById("user-name").value,
            email: document.getElementById("user-email").value,
            password: document.getElementById("default-password").value,
            is_admin: document.getElementById("is-admin").value
        })
    });

    loadUsers();
});

loadUsers();