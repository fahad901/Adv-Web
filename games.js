const game = $("#game");
const mode = game.dataset.game;

function element(tag, label, className) {
  const node = document.createElement(tag);
  node.textContent = label;
  if (className) node.className = className;
  return node;
}

function button(label, callback, kind = "secondary") {
  const control = element("button", label, kind);
  control.type = "button";
  control.addEventListener("click", callback);
  return control;
}

const statusLine = element("p", "");
statusLine.setAttribute("role", "status");
const board = element("div", "");
const controls = element("div", "", "actions");
const saveMessage = element("p", "", "message");
saveMessage.setAttribute("role", "status");
game.append(statusLine, board, controls, saveMessage);

function clearRoundMessage() {
  saveMessage.replaceChildren();
}

function record(score, outcome) {
  const roundId = crypto.randomUUID();
  const notice = element("span", "Saving result…");
  saveMessage.replaceChildren(notice);
  async function save() {
    try {
      const player = await session;
      if (!player) {
        const signIn = element("a", "Log in");
        signIn.href = "login.html";
        notice.replaceChildren(signIn, " before your next round to save results.");
        return;
      }
      const result = await api("results", {game: mode, score, outcome, roundId});
      notice.textContent = result.message;
    } catch {
      notice.replaceChildren("Result could not be saved. ", button("Retry", save));
    }
  }
  save();
}

function focusTile(index) {
  (board.children[index]?.disabled ? board.querySelector("button:not(:disabled)") : board.children[index])?.focus();
  if (!board.contains(document.activeElement)) controls.querySelector("button")?.focus();
}

function ticTacToe() {
  const lines = [[0,1,2], [3,4,5], [6,7,8], [0,3,6], [1,4,7], [2,5,8], [0,4,8], [2,4,6]];
  let tiles, turn, winning, done;
  board.className = "board";

  function reset() {
    tiles = Array(9).fill("");
    turn = "X";
    winning = [];
    done = false;
    statusLine.textContent = "X goes first.";
    clearRoundMessage();
    render();
  }
  function move(index) {
    if (done || tiles[index]) return;
    tiles[index] = turn;
    winning = lines.find((line) => line.every((position) => tiles[position] === turn)) || [];
    const draw = !winning.length && tiles.every(Boolean);
    done = Boolean(winning.length || draw);
    if (done) {
      const outcome = draw ? "Draw" : turn + " wins";
      statusLine.textContent = outcome + ".";
      record(outcome === "X wins" ? 1 : 0, outcome);
    } else {
      turn = turn === "X" ? "O" : "X";
      statusLine.textContent = turn + " to play.";
    }
    render();
    focusTile((index + 1) % 9);
  }
  function render() {
    board.replaceChildren(...tiles.map((mark, index) => {
      const tile = button(mark || "·", () => move(index), winning.includes(index) ? "winner" : mark === "O" ? "other" : "");
      tile.disabled = done || Boolean(mark);
      tile.setAttribute("aria-label", `Cell ${index + 1}: ${mark || "empty"}`);
      return tile;
    }));
  }

  controls.append(button("New match", reset));
  reset();
}

function slidePuzzle() {
  const solved = "1,2,3,4,5,6,7,8,0";
  let tiles, moves, empty, done;
  board.className = "board";

  function neighbors(index) {
    const row = Math.floor(index / 3);
    const col = index % 3;
    return [row > 0 ? index - 3 : -1, row < 2 ? index + 3 : -1,
      col > 0 ? index - 1 : -1, col < 2 ? index + 1 : -1].filter((value) => value >= 0);
  }
  function swap(index) {
    [tiles[index], tiles[empty]] = [tiles[empty], tiles[index]];
    empty = index;
  }
  function reset() {
    tiles = [1,2,3,4,5,6,7,8,0];
    empty = 8;
    let previous = -1;
    for (let step = 0; step < 31; step++) {
      const choices = neighbors(empty).filter((index) => index !== previous);
      previous = empty;
      swap(choices[Math.floor(Math.random() * choices.length)]);
    }
    moves = 0;
    done = false;
    statusLine.textContent = "Moves: 0";
    clearRoundMessage();
    render();
  }
  function play(index) {
    const target = empty;
    swap(index);
    moves++;
    done = tiles.join(",") === solved;
    statusLine.textContent = done ? `Solved in ${moves} moves!` : `Moves: ${moves}`;
    if (done) record(moves, "Solved");
    render();
    focusTile(target);
  }
  function render() {
    const choices = neighbors(empty);
    board.replaceChildren(...tiles.map((number, index) => {
      const tile = button(number ? String(number) : "", () => play(index), number ? "" : "empty");
      tile.disabled = done || !choices.includes(index);
      tile.setAttribute("aria-label", number ? `Tile ${number}` : "Empty space");
      return tile;
    }));
  }

  controls.append(button("Shuffle puzzle", reset));
  reset();
}

async function quiz() {
  let questions;
  let index, score;

  function render() {
    const {question, options: answers, correct} = questions[index];
    statusLine.textContent = `Question ${index + 1} of ${questions.length}`;
    const title = element("h2", question);
    title.tabIndex = -1;
    const options = element("div", "", "quiz-options");
    const response = element("p", "", "message");
    response.setAttribute("role", "status");
    answers.forEach((answer, position) => {
      const input = document.createElement("input");
      input.type = "radio";
      input.name = "answer";
      input.value = position;
      const label = element("label", "", "choice");
      label.append(input, answer);
      options.append(label);
    });
    const submit = button("Check answer", () => {
      const chosen = options.querySelector(":checked");
      if (!chosen) {
        feedback(response, "Choose an answer first.");
        options.querySelector("input").focus();
        return;
      }
      const right = Number(chosen.value) === correct;
      if (right) score++;
      options.querySelectorAll("input").forEach((input) => { input.disabled = true; });
      feedback(response, right ? "Correct." : `The correct answer is ${answers[correct]}.`, right);
      const next = button(index === questions.length - 1 ? "See result" : "Next question", () => {
        index++;
        if (index < questions.length) render().focus();
        else {
          statusLine.textContent = "Quiz complete";
          const result = element("h2", `You scored ${score} / ${questions.length}`);
          result.tabIndex = -1;
          board.replaceChildren(result);
          controls.replaceChildren(button("Play again", () => reset(true), "primary"));
          record(score, "Completed");
          result.focus();
        }
      }, "primary");
      controls.replaceChildren(next);
      next.focus();
    }, "primary");
    board.replaceChildren(title, options, response);
    controls.replaceChildren(submit);
    return title;
  }

  async function reset(focusQuestion = false) {
    statusLine.textContent = "Loading questions…";
    index = 0;
    score = 0;
    clearRoundMessage();
    board.replaceChildren();
    controls.replaceChildren();
    try {
      ({questions} = await api("questions"));
      if (!questions.length) {
        statusLine.textContent = "The quiz question bank is empty.";
        return;
      }
      const title = render();
      if (focusQuestion) title.focus();
    } catch {
      statusLine.textContent = "Questions are unavailable. Check that the Python server is running.";
      controls.append(button("Try again", () => reset(true)));
    }
  }
  reset();
}

({"tic-tac-toe": ticTacToe, "slide-puzzle": slidePuzzle, quiz})[mode]();

