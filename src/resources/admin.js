var resources = [];
var editId = null;

const resourceForm = document.querySelector("#resource-form");
const resourcesTbody = document.querySelector("#resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");

  // 
  const td1 = document.createElement("td");
  td1.textContent = resource.title;

  const td2 = document.createElement("td");
  td2.textContent = resource.description;

  const td3 = document.createElement("td");
  const link = document.createElement("a");
  link.href = resource.link;
  link.textContent = resource.link;
  td3.appendChild(link);

  const td4 = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;
  editBtn.textContent = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = resource.id;
  deleteBtn.textContent = "Delete";

  td4.appendChild(editBtn);
  td4.appendChild(deleteBtn);

  tr.appendChild(td1);
  tr.appendChild(td2);
  tr.appendChild(td3);
  tr.appendChild(td4);

  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = "";

  resources.forEach(function(resource) {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}
