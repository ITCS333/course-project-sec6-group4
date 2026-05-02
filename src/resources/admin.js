var resources = [];
var editId = null;

const resourceForm = document.querySelector("#resource-form");
const resourcesTbody = document.querySelector("#resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td><a href="${resource.link}" target="_blank">${resource.link}</a></td>
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
    resourcesTbody.appendChild(createResourceRow(resource));
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  const title = document.querySelector("#resource-title").value;
  const description = document.querySelector("#resource-description").value;
  const link = document.querySelector("#resource-link").value;

  if (editId) {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editId, title, description, link })
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.map(function(resource) {
        if (resource.id == editId) {
          return { id: editId, title, description, link };
        }
        return resource;
      });

      editId = null;
      document.querySelector("#add-resource").textContent = "Add Resource";
      resourceForm.reset();
      renderTable();
    }

    return;
  }

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, description, link })
  });

  const result = await response.json();

  if (result.success) {
    resources.push({
      id: result.id,
      title,
      description,
      link
    });

    resourceForm.reset();
    renderTable();
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: "DELETE"
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.filter(function(resource) {
        return resource.id != id;
      });

      renderTable();
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    const id = event.target.dataset.id;

    const resource = resources.find(function(resource) {
      return resource.id == id;
    });

    document.querySelector("#resource-title").value = resource.title;
    document.querySelector("#resource-description").value = resource.description;
    document.querySelector("#resource-link").value = resource.link;

    editId = id;
    document.querySelector("#add-resource").textContent = "Update Resource";
  }
}

async function loadAndInitialize() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  if (result.success) {
    resources = result.data;
    renderTable();
  }

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
