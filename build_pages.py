"""Regenerate the 15 standalone HTML pages after editing shared layouts or page copy."""
from html import escape
from pathlib import Path

PUBLIC = Path(__file__).resolve().parent / "public"
GAMES = (
    ("tic-tac-toe", "Tic-Tac-Toe", "Strategy", "Play a local two-player match on a three-by-three board.", "×"),
    ("slide-puzzle", "Slide Puzzle", "Logic", "Slide the tiles into order in as few moves as possible.", "▦"),
    ("quiz", "Quiz Challenge", "Trivia", "Test your knowledge with questions from the arcade question bank.", "?"),
)
GAME_OPTIONS = "".join(f'<option value="{slug}">{title}</option>' for slug, title, *_ in GAMES)


def link(page, label, style=""):
    return f'<a class="{style}" href="{page}.html">{label}</a>'


def cards():
    return '<div class="cards">' + "".join(
        f'<a href="{slug}.html" class="game-card {slug}"><div class="game-art" aria-hidden="true"><span>{symbol}</span></div>'
        f'<div class="card-copy"><span class="category">{genre}</span><h3>{title}<span aria-hidden="true">↗</span></h3><p>{description}</p></div></a>'
        for slug, title, genre, description, symbol in GAMES
    ) + '</div>'


def field(name, label, kind="text", extra=""):
    return f'<label>{label}<input name="{name}" type="{kind}" required {extra}></label>'


def form(action, fields, button):
    return (f'<form class="form" data-action="{action}">{fields}'
            f'<button class="primary" type="submit">{button}</button><p class="message" role="status"></p></form>')


def table(headers, empty, element):
    heading = "".join(f'<th scope="col">{heading}</th>' for heading in headers)
    return (f'<div class="table-wrap"><table><thead><tr>{heading}</tr></thead>'
            f'<tbody id="{element}"><tr><td colspan="{len(headers)}">{empty}</td></tr></tbody></table></div>')


EMAIL = field("email", "Email", "email", 'autocomplete="email" maxlength="120"')
PASSWORD = field("password", "Password", "password", 'autocomplete="new-password" minlength="8" maxlength="128"')
NAME = field("name", "Display name", extra='autocomplete="nickname" maxlength="40"')
BIO = '<label>Bio <span class="optional">optional</span><textarea name="bio" maxlength="240" rows="3"></textarea></label>'
QUESTION_FIELDS = (
    field("question", "Question", extra='maxlength="500"')
    + "".join(field(letter.lower(), f"Option {letter}", extra='maxlength="255"') for letter in "ABCD")
    + '<label>Correct option<select name="correct">' + "".join(
        f'<option value="{index}">{letter}</option>' for index, letter in enumerate("ABCD")
    ) + '</select></label>'
)
HERO_BOARD = "".join(f'<span class="{cls}">{glyph}</span>' for cls, glyph in (
    ("", ""), ("mark-o", "○"), ("", ""), ("mark-x", "×"), ("mark-x main-mark", "×"),
    ("mark-x", "×"), ("", ""), ("mark-o", "○"), ("", ""),
))

PAGES = {
    "index": ("Home", "Arcade", """
        <section class="hero">
          <div class="hero-copy">
            <h1>Three games.<br><em>One arcade.</em></h1>
            <p>Play a quick match, solve a sliding puzzle, or put your computing knowledge to the test.</p>
            <div class="actions"><a class="primary" href="games.html">Explore games <span aria-hidden="true">↗</span></a><a class="secondary" href="leaderboard.html">View leaderboard</a></div>
          </div>
          <div class="hero-art" aria-hidden="true"><div class="orbit"></div><div class="art-board">""" + HERO_BOARD + """</div></div>
        </section>
        <section class="lineup" aria-labelledby="lineup"><div class="section-head"><h2 id="lineup">Game lineup</h2><a href="games.html">Browse all games <span aria-hidden="true">→</span></a></div>""" + cards() + "</section>"),
    "games": ("Game Library", "Play", '<p class="intro">Choose a game and jump straight in.</p>' + cards()),
    **{
        slug: (
            title,
            "Play",
            '<div class="game-layout">'
            '<section class="panel game-panel">'
            f'<div id="game" data-game="{slug}"></div>'
            '</section>'
            '<aside class="panel instructions">'
            f'<h2>How to play</h2><p>{description}</p><p>{rules}</p>'
            + link("games", "← All games")
            + '</aside></div>',
        )
        for (slug, title, _, description, _), rules in zip(GAMES, (
            "Take turns placing X and O. Three in a row wins. A full board with no winner is a draw. If signed in, you play as X for leaderboard scoring.",
            "Move a tile that touches the empty space. Arrange 1 through 8 in order, with the empty space in the bottom right.",
            "Choose an answer and submit it to see feedback. Each correct answer earns one point.",
        ))
    },
    'leaderboard': (
        'Leaderboard',
        'Results',
        (
            '<p class="intro">Compare top scores by game. For Slide Puzzle, fewer moves ranks higher.</p>'
            '<div class="panel">'
            '<label class="inline-label">Game <select id="leader-game">'
        )
        + GAME_OPTIONS
        + '</select></label>'
        + table(("Rank", "Player", "Result"), "Loading scores…", "leaders")
        + '</div>',
    ),
    'history': (
        'Game History',
        'Results',
        '<p class="intro">Review the rounds saved to your account.</p><div class="panel" data-private>'
        + table(("Game", "Result", "Played"), "Loading history…", "history-list")
        + '</div>',
    ),
    'login': (
        'Log In',
        'Account',
        '<div class="panel auth">'
        + form("login", EMAIL + field("password", "Password", "password", 'autocomplete="current-password" maxlength="128"'), "Log in")
        + '<div class="auth-links">'
        + link("forgot-password", "Forgot password?")
        + link("register", "Create an account")
        + '</div></div>',
    ),
    'register': (
        'Create Account',
        'Account',
        '<p class="intro">Create an account to save scores and game history.</p><div class="panel auth">'
        + form("register", NAME + EMAIL + PASSWORD, "Create account")
        + '<div class="auth-links">'
        + link("login", "Already registered? Log in")
        + '</div></div>',
    ),
    'forgot-password': (
        'Forgot Password',
        'Account',
        (
            '<p class="intro">Request a one-time reset code. In this local demo, codes appear in the Python terminal.</p>'
            '<div class="panel auth">'
        )
        + form("forgot", EMAIL, "Request reset code")
        + '<div class="auth-links">'
        + link("reset-password", "Enter a reset code")
        + link("login", "Back to log in")
        + '</div></div>',
    ),
    'reset-password': (
        'Reset Password',
        'Account',
        '<div class="panel auth">'
        + form("reset", EMAIL + field("code", "Reset code", extra='autocomplete="one-time-code" maxlength="64"') + PASSWORD, "Set new password")
        + '<div class="auth-links">'
        + link("forgot-password", "Request a new code")
        + '</div></div>',
    ),
    'profile': (
        'User Profile',
        'Account',
        (
            '<div class="panel profile" data-private>'
            '<div class="avatar" id="avatar" aria-hidden="true">'
            '</div>'
            '<div>'
            '<h2 id="profile-name">Loading profile…</h2>'
            '<p id="profile-email">'
            '</p>'
            '<p id="profile-bio">'
            '</p>'
            '<div class="actions">'
        )
        + link("edit-profile", "Edit profile", "primary")
        + link("history", "Game history", "secondary")
        + (
            '<button class="secondary" id="logout" type="button">Log out</button>'
            '</div>'
            '<p id="logout-message" class="message" role="status">'
            '</p>'
            '</div>'
            '</div>'
        ),
    ),
    'edit-profile': (
        'Edit Profile',
        'Account',
        '<div class="panel auth" data-private>'
        + form("profile", NAME + BIO, "Save changes")
        + '<div class="auth-links">'
        + link("profile", "Back to profile")
        + '</div></div>',
    ),
    'admin': (
        'Admin Dashboard',
        'Administration',
        (
            '<div data-admin class="panel">'
            '<h2>Overview</h2>'
            '<div id="admin-metrics" class="metrics" aria-live="polite">Loading dashboard…</div>'
            '<h2>Manage quiz questions</h2>'
        )
        + form("admin/add-question", QUESTION_FIELDS, "Add question")
        + table(("Question", "Action"), "Loading questions…", "admin-questions")
        + '<p id="question-message" class="message" role="status"></p><h2>Players</h2>'
        + table(("Name", "Email", "Role"), "Loading players…", "admin-users")
        + '<h2>Contact messages</h2>'
        + table(("Email", "Message", "Received"), "Loading messages…", "admin-messages")
        + '</div>',
    ),
    'about': (
        'About & Contact',
        'Arcade',
        (
            '<div class="game-layout">'
            '<section class="panel">'
            '<h2>About Gridline Arcade</h2>'
            '<p>Gridline Arcade is an Advanced Web Development project with three browser games. HTML and CSS define the pages, JavaScript handles gameplay, and Python runs the local server and demo accounts.</p>'
            '<h2>Contact the project team</h2>'
            '<p>Use the form for game feedback or project questions.</p>'
            '</section>'
            '<section class="panel auth">'
        )
        + form("contact", EMAIL + '<label>Message<textarea name="message" rows="5" required maxlength="1000"></textarea></label>', "Send message")
        + '</section></div>',
    ),
}
NAV = (
    ("Discover", (("index", "Overview", "⌂"), ("games", "Game library", "▦"), ("leaderboard", "Leaderboard", "▤"), ("history", "Game history", "◷"))),
    ("Games", (("tic-tac-toe", "Tic-Tac-Toe", "×"), ("slide-puzzle", "Slide Puzzle", "⊞"), ("quiz", "Quiz Challenge", "?"))),
    ("Account", (("profile", "My profile", "◎"), ("admin", "Admin", "◇"), ("about", "About & contact", "ⓘ"))),
)


def navigation(current):
    groups = []
    for heading, items in NAV:
        links = []
        for slug, label, icon in items:
            active = ' aria-current="page"' if slug == current else ""
            links.append(f'<a href="{slug}.html"{active}><span aria-hidden="true">{icon}</span>{escape(label)}</a>')
        group_links = "\n          ".join(links)
        groups.append(
            f'<div class="nav-group">\n          <p>{heading}</p>'
            f'\n          {group_links}\n        </div>'
        )
    return "\n        ".join(groups)


def render(slug, title, group, content):
    heading = "" if slug == "index" else f'<header class="page-heading"><p class="category">{group}</p><h1>{escape(title)}</h1></header>'
    return f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{escape(title)} | Gridline Arcade</title>
  <link rel="stylesheet" href="assets/styles.css">
  <script src="assets/app.js" defer></script>
  {"<script src='assets/games.js' defer></script>" if slug in {game[0] for game in GAMES} else ""}
</head>
<body data-page="{slug}">
<a class="skip" href="#main">Skip to content</a>
<header class="topbar">
  <button id="menu" class="menu secondary" type="button" aria-label="Navigation menu"
    aria-expanded="false" aria-controls="sidebar"><span class="menu-icon" aria-hidden="true"></span></button>
  <a class="brand" href="index.html"><span class="brand-icon" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span>Gridline <b>Arcade</b></span></a>
  <div class="top-actions" id="account-links">
    {link("login", "Log in")}
    <a class="secondary register-link" href="register.html" aria-label="Create account">
      <span class="desktop-label">Create account</span><span class="mobile-label" aria-hidden="true">Join</span>
    </a>
  </div>
</header>
<div class="shell">
  <aside id="sidebar" class="sidebar">
    <nav aria-label="Main navigation">
        {navigation(slug)}
    </nav>
  </aside>
  <main id="main" tabindex="-1">
    {heading}
    {content}
    <footer><span>Gridline Arcade</span>{link("about", "About & contact")}</footer>
  </main>
</div>
</body>
</html>
"""


if __name__ == "__main__":
    PUBLIC.mkdir(exist_ok=True)
    for slug, (title, group, content) in PAGES.items():
        (PUBLIC / f"{slug}.html").write_text(render(slug, title, group, content), encoding="utf-8")

