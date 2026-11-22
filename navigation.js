const menu = document.querySelector("#menu");
const sidebar = document.querySelector("#sidebar");
const mobileLayout = window.matchMedia("(max-width: 760px)");

function showMenu(open) {
  sidebar.classList.toggle("open", open);
  menu.setAttribute("aria-expanded", String(open));
}

menu.addEventListener("click", () => {
  const open = !sidebar.classList.contains("open");
  showMenu(open);
  if (open) {
    (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector("a")).focus();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && sidebar.classList.contains("open")) {
    showMenu(false);
    menu.focus();
  }
});

document.addEventListener("pointerdown", (event) => {
  if (sidebar.classList.contains("open") && !sidebar.contains(event.target) && !menu.contains(event.target)) {
    showMenu(false);
  }
});

mobileLayout.addEventListener("change", () => {
  if (!mobileLayout.matches) {
    showMenu(false);
  }
});

