const $ = (selector) => document.querySelector(selector);

async function api(action, body) {
  const options = body === undefined ? {} : {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify(body)
  };
  const response = await fetch("/api/" + action, options);
  const data = await response.json();
  if (!response.ok) throw new Error(data.error);
  return data;
}

function feedback(node, message, success = false) {
  node.textContent = message;
  node.className = success ? "message success" : "message error";
}

const games = {"tic-tac-toe": "Tic-Tac-Toe", "slide-puzzle": "Slide Puzzle", quiz: "Quiz Challenge"};
const gameResult = (game, score, outcome = "") => ({
  "tic-tac-toe": outcome || `${score} wins`,
  "slide-puzzle": `${score} moves`,
  quiz: `${score} correct`
})[game];

function showRows(id, rows, columns, empty) {
  const list = document.getElementById(id);
  list.replaceChildren();
  if (!rows.length) {
    const cell = list.insertRow().insertCell();
    cell.colSpan = columns;
    cell.textContent = empty;
    return;
  }
  for (const values of rows) {
    const row = list.insertRow();
    for (const value of values) row.insertCell().textContent = value;
  }
}

function accessNotice(container, text, signIn) {
  const message = document.createElement("p");
  message.textContent = text;
  container.replaceChildren(message);
  if (signIn) {
    const link = document.createElement("a");
    link.href = "login.html";
    link.textContent = "Log in";
    container.append(link);
  }
}

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

document.querySelectorAll("form[data-action]").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = form.querySelector("button[type=submit]");
    const message = form.querySelector(".message");
    const action = form.dataset.action;
    submit.disabled = true;
    try {
      const result = await api(action, Object.fromEntries(new FormData(form)));
      if (action === "login") {
        location.href = "profile.html";
        return;
      }
      feedback(message, result.message, true);
      if (action !== "profile") form.reset();
      if (action === "admin/add-question") await loadAdmin();
    } catch (error) {
      feedback(message, error.message);
    } finally {
      submit.disabled = false;
    }
  });
});

const session = api("session").then(({user}) => {
  if (user) {
    const profile = document.createElement("a");
    profile.className = "profile-link";
    profile.href = "profile.html";
    profile.setAttribute("aria-label", "My profile");

    const icon = document.createElement("span");
    icon.className = "profile-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = Array.from(user.name)[0]?.toUpperCase() || "P";
    const label = document.createElement("span");
    label.className = "profile-link-label";
    label.textContent = "My profile";
    profile.append(icon, label);
    $("#account-links").replaceChildren(profile);
  }
  return user;
});

async function loadAdmin() {
  const {players, results, messages, questions} = await api("admin");
  const metrics = [`${players.length} accounts`, `${results} rounds`, `${messages.length} messages`].map((label) => {
    const item = document.createElement("span");
    item.textContent = label;
    return item;
  });
  $("#admin-metrics").replaceChildren(...metrics);
  showRows("admin-users", players.map(({name, email, role}) => [name, email, role]), 3, "No players.");
  showRows("admin-messages", messages.map(({email, message, played}) => [email, message, new Date(played).toLocaleString()]), 3, "No messages yet.");
  showRows("admin-questions", questions.map(({question}) => [question, ""]), 2, "No questions in the bank.");
  questions.forEach(({id, question}, index) => {
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "secondary";
    remove.textContent = "Delete";
    remove.setAttribute("aria-label", "Delete question: " + question);
    remove.addEventListener("click", async () => {
      if (!confirm("Delete this quiz question?")) return;
      remove.disabled = true;
      try {
        await api("admin/delete-question", {id});
        await loadAdmin();
        feedback($("#question-message"), "Question deleted.", true);
        $("input[name=question]").focus();
      } catch (error) {
        feedback($("#question-message"), error.message);
        remove.disabled = false;
      }
    });
    $("#admin-questions").rows[index].cells[1].append(remove);
  });
}

async function setupPage() {
  const page = document.body.dataset.page;
  const container = $("[data-private], [data-admin]");
  let user;
  try {
    user = await session;
  } catch {
    if (container) accessNotice(container, "Start the Python server to access this page.", false);
  }
  if (container && user === undefined) return;
  if (container && !user) {
    accessNotice(container, "Log in to access this page.", true);
    return;
  }
  try {
    if (page === "profile") {
      $("#avatar").textContent = Array.from(user.name).slice(0, 2).join("").toUpperCase();
      $("#profile-name").textContent = user.name;
      $("#profile-email").textContent = user.email;
      $("#profile-bio").textContent = user.bio || "No bio added yet.";
      $("#logout").addEventListener("click", async () => {
        try {
          await api("logout", {});
          location.href = "index.html";
        } catch (error) {
          feedback($("#logout-message"), error.message);
        }
      });
    }
    if (page === "edit-profile") {
      $("input[name=name]").value = user.name;
      $("textarea[name=bio]").value = user.bio;
    }
    if (page === "history") {
      const {rows} = await api("history");
      showRows("history-list", rows.map(({game, score, outcome, played}) => [
        games[game], gameResult(game, score, outcome), new Date(played).toLocaleString()
      ]), 3, "Play a game while logged in to see results here.");
    }
    if (page === "admin") await loadAdmin();
  } catch (error) {
    if (container) accessNotice(container, error.message, false);
  }
}

const gameFilter = $("#leader-game");
if (gameFilter) {
  let requestId = 0;
  const loadScores = async () => {
    const id = ++requestId;
    const game = gameFilter.value;
    showRows("leaders", [], 3, "Loading scores…");
    try {
      const {rows} = await api("leaderboard?game=" + encodeURIComponent(game));
      if (id !== requestId) return;
      showRows("leaders", rows.map(({name, score}, index) => [index + 1, name, gameResult(game, score)]), 3, "No scores yet. Play while logged in to add yours.");
    } catch {
      if (id === requestId) showRows("leaders", [], 3, "Scores are unavailable. Check that the Python server is running.");
    }
  };
  gameFilter.addEventListener("change", loadScores);
  loadScores();
}
setupPage();

