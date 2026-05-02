let resources = [];
let editId = null;

const resourceForm = document.querySelector("#resource-form");
const resourcesTbody = document.querySelector("#resources-tbody");

function createResourceRow(resource) {
  let tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td><a href="${resource.link}">Open Link</a></td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
    </td>
  `;

  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = "";

  resources.forEach(function(resource) {
    let row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  let title = document.getElementById("resource-title").value;
  let description = document.getElementById("resource-description").value;
  let link = document.getElementById("resource-link").value;

  if (editId !== null) {
    let response = await fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editId, title, description, link })
    });

    let result = await response.json();

    if (result.success) {
      resources = resources.map(function(resource) {
        if (resource.id == editId) {
          return { id: editId, title, description, link };
        }
        return resource;
      });

      editId = null;
      document.getElementById("add-resource").textContent = "Add Resource";
      renderTable();
      resourceForm.reset();
    }

  } else {
    let response = await fetch("./api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description, link })
    });

    let result = await response.json();

    if (result.success) {
      resources.push({
        id: result.id,
        title: title,
        description: description,
        link: link
      });

      renderTable();
      resourceForm.reset();
    }
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    let id = event.target.dataset.id;

    let response = await fetch(`./api/index.php?id=${id}`, {
      method: "DELETE"
    });

    let result = await response.json();

    if (result.success) {
      resources = resources.filter(function(resource) {
        return resource.id != id;
      });

      renderTable();
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    let id = event.target.dataset.id;

    let resource = resources.find(function(resource) {
      return resource.id == id;
    });

    document.getElementById("resource-title").value = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value = resource.link;

    editId = id;
    document.getElementById("add-resource").textContent = "Update Resource";
  }
}

async function loadAndInitialize() {
  let response = await fetch("./api/index.php");
  let result = await response.json();

  if (result.success) {
    resources = result.data;
    renderTable();
  }

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
