function handleSort(event) {
  const th = event.currentTarget;
  const index = th.cellIndex;

  let key = "";
  if (index === 0) key = "name";
  else if (index === 1) key = "email";
  else if (index === 2) key = "is_admin";

  // toggle direction (asc -> desc -> asc)
  let dir = th.dataset.sortDir || "asc";

  // أول ضغط = asc، ثاني ضغط = desc
  dir = dir === "asc" ? "desc" : "asc";
  th.dataset.sortDir = dir;

  users.sort((a, b) => {
    let valA = a[key];
    let valB = b[key];

    // string sorting
    if (key === "name" || key === "email") {
      valA = (valA || "").toLowerCase();
      valB = (valB || "").toLowerCase();

      return dir === "asc"
        ? valA.localeCompare(valB)
        : valB.localeCompare(valA);
    }

    // number sorting (is_admin)
    return dir === "asc"
      ? Number(valA) - Number(valB)
      : Number(valB) - Number(valA);
  });

  renderTable(users);
}