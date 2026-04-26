// --- Global Data Store ---
let assignments = [];

// --- Element Selections ---
const form = document.getElementById('assignment-form');
const tableBody = document.getElementById('assignments-tbody');
const submitBtn = document.getElementById('add-assignment');

// --- Functions ---

function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');

  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
    <td>
      <button class="edit-btn" data-id="${assignment.id}">Edit</button>
      <button class="delete-btn" data-id="${assignment.id}">Delete</button>
    </td>
  `;

  return tr;
}

function renderTable() {
  tableBody.innerHTML = '';

  assignments.forEach(a => {
    const row = createAssignmentRow(a);
    tableBody.appendChild(row);
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title = document.getElementById('assignment-title').value;
  const due_date = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value;

  const files = document
    .getElementById('assignment-files')
    .value.split('\n')
    .map(f => f.trim())
    .filter(f => f !== '');

  const editId = submitBtn.dataset.editId;

  // EDIT MODE
  if (editId) {
    await handleUpdateAssignment(editId, { title, due_date, description, files });
    return;
  }

  // CREATE
  const res = await fetch('./api/index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ title, due_date, description, files })
  });

  const result = await res.json();

  if (result.success) {
    assignments.push({
      id: result.id,
      title,
      due_date,
      description,
      files
    });

    renderTable();
    form.reset();
  }
}

async function handleUpdateAssignment(id, fields) {
  const res = await fetch('./api/index.php', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ id, ...fields })
  });

  const result = await res.json();

  if (result.success) {
    const index = assignments.findIndex(a => a.id == id);

    if (index !== -1) {
      assignments[index] = { id, ...fields };
    }

    renderTable();
    form.reset();

    submitBtn.textContent = "Add Assignment";
    delete submitBtn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const id = event.target.dataset.id;

  // DELETE
  if (event.target.classList.contains('delete-btn')) {
    await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    assignments = assignments.filter(a => a.id != id);
    renderTable();
  }

  // EDIT
  if (event.target.classList.contains('edit-btn')) {
    const assignment = assignments.find(a => a.id == id);

    if (!assignment) return;

    document.getElementById('assignment-title').value = assignment.title;
    document.getElementById('assignment-due-date').value = assignment.due_date;
    document.getElementById('assignment-description').value = assignment.description;
    document.getElementById('assignment-files').value = assignment.files.join('\n');

    submitBtn.textContent = "Update Assignment";
    submitBtn.dataset.editId = id;
  }
}

async function loadAndInitialize() {
  const res = await fetch('./api/index.php');
  const result = await res.json();

  if (result.success) {
    assignments = result.data;
    renderTable();
  }

  form.addEventListener('submit', handleAddAssignment);
  tableBody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();