const resourceListSection = document.querySelector("#resource-list-section");

function createResourceArticle(resource) {
    let article = document.createElement("article");

    article.innerHTML = `
        <h2>${resource.title}</h2>
        <p>${resource.description}</p>
        <a href="details.html?id=${resource.id}">View Resource & Discussion</a>
    `;

    return article;
}

async function loadResources() {
    let response = await fetch("./api/index.php");
    let result = await response.json();

    if (result.success) {
        resourceListSection.innerHTML = "";

        result.data.forEach(function(resource) {
            let article = createResourceArticle(resource);
            resourceListSection.appendChild(article);
        });
    }
}

loadResources();
