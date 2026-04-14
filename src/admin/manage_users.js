function handleSort(event) {
  const th = event.currentTarget;
  const index = th.cellIndex;

  let key = "";
  if (index === 0) key = "name";
  else if (index === 1) key = "email";
  else if (index === 2) key = "is_admin";


  let dir = th.dataset.sortDir === "asc" ? "desc" : "asc";
  th.dataset.sortDir = dir;

  users.sort((a, b) => {
    let valA = a[key];
    let valB = b[key];


    if (key === "name" || key === "email") {
      if (dir === "asc") {
        return valA.localeCompare(valB);
      } else {
        return valB.localeCompare(valA);
      }
    }

    if (dir === "asc") {
      return valA - valB;
    } else {
      return valB - valA;
    }
  });

  renderTable(users);
}