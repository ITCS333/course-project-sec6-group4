/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.
*/

// --- Global Data Store ---
let weeks = [];

// --- Element Selections ---
const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

// --- Functions ---

function createWeekRow(week) {
  const tr = document.createElement('tr');

  tr.innerHTML = `
    <td>${week.title}</td>
    <td>${week.start_date}</td>
    <td>${week.description}</td>
    <td>
      <button class="edit-btn"   data-id="${week.id}">Edit</button>
      <button class="delete-btn" data-id="${week.id}">Delete</button>
    </td>
  `;

  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = '';
  weeks.forEach(week => {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title       = document.getElementById('week-title').value;
  const start_date  = document.getElementById('week-start-date').value;
  const description = document.getElementById('week-description').value;
  const links       = document.getElementById('week-links').value
                        .split('\n')
                        .filter(link => link.trim() !== '');

  const submitBtn = document.getElementById('add-week');
  const editId    = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateWeek(Number(editId), { title, start_date, description, links });
  } else {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, start_date, description, links })
    });

    const result = await response.json();

    if (result.success) {
      weeks.push({ id: result.id, title, start_date, description, links });
      renderTable();
      weekForm.reset();
    }
  }
}

async function handleUpdateWeek(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields })
  });

  const result = await response.json();

  if (result.success) {
    weeks = weeks.map(w => w.id === id ? { id, ...fields } : w);
    renderTable();
    weekForm.reset();

    const submitBtn = document.getElementById('add-week');
    submitBtn.textContent = 'Add Week';
    delete submitBtn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;
  const id     = Number(target.dataset.id);

  if (target.classList.contains('delete-btn')) {
    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success) {
      weeks = weeks.filter(w => w.id !== id);
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const week = weeks.find(w => w.id === id);

    document.getElementById('week-title').value       = week.title;
    document.getElementById('week-start-date').value  = week.start_date;
    document.getElementById('week-description').value = week.description;
    document.getElementById('week-links').value       = week.links.join('\n');

    const submitBtn = document.getElementById('add-week');
    submitBtn.textContent      = 'Update Week';
    submitBtn.dataset.editId   = week.id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  if (result.success) {
    weeks = result.data;
    renderTable();
  }

  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
