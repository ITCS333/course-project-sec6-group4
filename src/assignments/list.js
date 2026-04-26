// --- Element Selections ---
const section = document.getElementById('assignment-list-section');

// --- Functions ---

function createAssignmentArticle(assignment) {
  const article = document.createElement('article');

  const title = document.createElement('h2');
  title.textContent = assignment.title;

  const due = document.createElement('p');
  due.textContent = `Due: ${assignment.due_date}`;

  const desc = document.createElement('p');
  desc.textContent = assignment.description;

  const link = document.createElement('a');
  link.href = `details.html?id=${assignment.id}`;
  link.textContent = 'View Details & Discussion';

  article.appendChild(title);
  article.appendChild(due);
  article.appendChild(desc);
  article.appendChild(link);

  return article;
}

async function loadAssignments() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    // clear existing content
    section.innerHTML = '';

    if (result.success && Array.isArray(result.data)) {
      result.data.forEach(assignment => {
        const article = createAssignmentArticle(assignment);
        section.appendChild(article);
      });
    } else {
      section.textContent = 'Failed to load assignments.';
    }

  } catch (error) {
    console.error(error);
    section.textContent = 'Error loading assignments.';
  }
}

// --- Initial Page Load ---
loadAssignments();