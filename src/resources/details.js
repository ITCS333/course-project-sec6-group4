let currentResourceId = null;
let currentComments = [];

const resourceTitle = document.querySelector("#resource-title");
const resourceDescription = document.querySelector("#resource-description");
const resourceLink = document.querySelector("#resource-link");
const commentList = document.querySelector("#comment-list");
const commentForm = document.querySelector("#comment-form");
const newComment = document.querySelector("#new-comment");

function getResourceIdFromURL() {
    let params = new URLSearchParams(window.location.search);
    return params.get("id");
}

function renderResourceDetails(resource) {
    resourceTitle.textContent = resource.title;
    resourceDescription.textContent = resource.description;
    resourceLink.href = resource.link;
}

function createCommentArticle(comment) {
    let article = document.createElement("article");

    article.innerHTML = `
        <p>${comment.text}</p>
        <footer>Posted by: ${comment.author}</footer>
    `;

    return article;
}

function renderComments() {
    commentList.innerHTML = "";

    currentComments.forEach(function(comment) {
        let article = createCommentArticle(comment);
        commentList.appendChild(article);
    });
}

async function handleAddComment(event) {
    event.preventDefault();

    let commentText = newComment.value.trim();

    if (commentText == "") {
        return;
    }

    let response = await fetch("./api/index.php?action=comment", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            resource_id: currentResourceId,
            author: "Student",
            text: commentText
        })
    });

    let result = await response.json();

    if (result.success) {
        currentComments.push(result.data);
        renderComments();
        newComment.value = "";
    }
}

async function initializePage() {
    currentResourceId = getResourceIdFromURL();

    if (!currentResourceId) {
        resourceTitle.textContent = "Resource not found.";
        return;
    }

    let responses = await Promise.all([
        fetch(`./api/index.php?id=${currentResourceId}`),
        fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    let resourceResult = await responses[0].json();
    let commentsResult = await responses[1].json();

    if (resourceResult.success && resourceResult.data) {
        currentComments = commentsResult.data || [];

        renderResourceDetails(resourceResult.data);
        renderComments();

        commentForm.addEventListener("submit", handleAddComment);
    } else {
        resourceTitle.textContent = "Resource not found.";
    }
}

initializePage();
