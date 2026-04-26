// --- Global Data Store ---
let currentAssignmentId = null;
let currentComments     = [];

// --- Element Selections ---
const assignmentTitle       = document.getElementById('assignment-title');
const assignmentDueDate     = document.getElementById('assignment-due-date');
const assignmentDescription = document.getElementById('assignment-description');
const assignmentFilesList   = document.getElementById('assignment-files-list');
const commentList           = document.getElementById('comment-list');
const commentForm           = document.getElementById('comment-form');
const newCommentInput       = document.getElementById('new-comment');

// --- Functions ---

function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderAssignmentDetails(assignment) {
  assignmentTitle.textContent       = assignment.title;
  assignmentDueDate.textContent     = "Due: " + assignment.due_date;
  assignmentDescription.textContent = assignment.description;

  assignmentFilesList.innerHTML = '';

  assignment.files.forEach(url => {
    const li = document.createElement('li');

    const link = document.createElement('a');
    link.href = url;
    link.textContent = url;
    link.target = "_blank";

    li.appendChild(link);
    assignmentFilesList.appendChild(li);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement('article');

  const text = document.createElement('p');
  text.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(text);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.innerHTML = '';

  currentComments.forEach(comment => {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();
  if (!commentText) return;

  try {
    const response = await fetch('./api/index.php?action=comment', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        assignment_id: parseInt(currentAssignmentId),
        author: "Student",
        text: commentText
      })
    });

    const result = await response.json();

    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentInput.value = '';
    }

  } catch (err) {
    console.error(err);
  }
}

async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();

  if (!currentAssignmentId) {
    assignmentTitle.textContent = "Assignment not found.";
    return;
  }

  try {
    const [assignmentRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentAssignmentId}`),
      fetch(`./api/index.php?action=comments&assignment_id=${currentAssignmentId}`)
    ]);

    const assignmentData = await assignmentRes.json();
    const commentsData   = await commentsRes.json();

    if (!assignmentData.success || !assignmentData.data) {
      assignmentTitle.textContent = "Assignment not found.";
      return;
    }

    const assignment = assignmentData.data;

    currentComments = commentsData.success ? commentsData.data : [];

    renderAssignmentDetails(assignment);
    renderComments();

    commentForm.addEventListener('submit', handleAddComment);

  } catch (err) {
    console.error(err);
    assignmentTitle.textContent = "Error loading assignment.";
  }
}

// --- Initial Page Load ---
initializePage();