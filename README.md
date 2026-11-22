# Gridline Arcade

Advanced Web Development · Project Part 2

Gridline Arcade is a 15-page arcade website with Tic-Tac-Toe, Slide Puzzle, and Quiz Challenge. It uses HTML and CSS for the interface, JavaScript for gameplay and form interactions, and Python for the local server and account features.

## Run it in VS Code on Windows

1. Extract the ZIP and open the **part2** folder in VS Code.
2. From **Terminal → New Terminal**, run `py server.py`. If your installation uses the `python` command, run `python server.py`.
3. Open **http://127.0.0.1:8000/** in your browser. Keep the terminal open while you use the site.
4. Stop the server with **Ctrl+C**.

Requires Python 3.10 or later. There are no third-party packages to install. If port 8000 is busy, run `py server.py --port 8001` and open **http://127.0.0.1:8001/**.

## Page inventory

| # | Page | Source | Feature |
|---|---|---|---|
| 1 | Home | public/index.html | Project landing and featured games |
| 2 | Game Library | public/games.html | Browse all three games |
| 3 | Tic-Tac-Toe | public/tic-tac-toe.html | Local two-player matches |
| 4 | Slide Puzzle | public/slide-puzzle.html | Solvable shuffle and move counter |
| 5 | Quiz Challenge | public/quiz.html | Up to five randomized questions with feedback and a score |
| 6 | Leaderboard | public/leaderboard.html | Per-game rankings |
| 7 | Game History | public/history.html | Signed-in player's completed rounds |
| 8 | Log In | public/login.html | Account authentication |
| 9 | Create Account | public/register.html | Player registration |
| 10 | Forgot Password | public/forgot-password.html | Request a one-time reset code |
| 11 | Reset Password | public/reset-password.html | Enter a reset code and new password |
| 12 | User Profile | public/profile.html | View profile and sign out |
| 13 | Edit Profile | public/edit-profile.html | Update display name and bio |
| 14 | Admin Dashboard | public/admin.html | Admin-only account overview and quiz question management |
| 15 | About & Contact | public/about.html | Project description and feedback form |

## Demo walkthrough

- Play any game as a guest. Create an account and log in before starting a round to save its result to Game History and the Leaderboard. In Tic-Tac-Toe, the signed-in player is X for scoring. An X win earns one point. A draw or an O win earns zero.
- To demonstrate password reset, request a code from **Forgot Password**, copy the code printed in the Python terminal, and enter it on **Reset Password**. Each code expires after 10 minutes and can be used once.
- To demonstrate the Admin Dashboard, stop the server and run `py server.py --create-admin admin@example.test`. Enter a password at the prompt, then restart the server. Log in with that email and password and select **Admin** in the sidebar. Add or delete quiz questions there. The quiz draws up to five questions from the current question bank.
- Submit a sample contact message under **About & Contact** to see it in the admin dashboard.
- The Python server creates the local `data/arcade.json` file when you first save something. It stores password hashes, profile fields, game results and contact messages. Sessions and reset codes expire and are cleared on server restart. The app is designed to run on your computer for the class demo.

## Part 3 integration

The 15 HTML files are complete pages under `public/`. The UI uses shared styles in `public/assets/styles.css`. To edit shared HTML navigation, layout, or page copy, modify `build_pages.py` and run `py build_pages.py` to regenerate the HTML. Keep HTML edits in the generator if you plan to regenerate.

Gameplay is in `public/assets/games.js`. The JavaScript UI calls JSON endpoints through the single `api()` helper in `public/assets/app.js`. Python defines those routes and stores local data in `server.py`.

The parent folder retains the original PHP/MySQL edition and its 15 corresponding PHP routes. Its root README explains the XAMPP setup. For Part 3, use the existing PHP routes and SQL schema, or implement equivalent JSON endpoints for this edition's HTML pages. Replace local storage with database tables for users, game results, contact messages, quiz questions, and password resets. Use a user ID as the result owner and a unique user-and-round key to avoid duplicates on retries. PHP should handle session authorization and deliver one-time reset tokens through the configured email service. Scores originate in the browser for this local demo. A public leaderboard should verify results server-side.

