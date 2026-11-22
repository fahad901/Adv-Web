"""Local Part 2 web server with JSON persistence and a small HTTP API."""
import argparse
from datetime import datetime, timezone
from functools import partial
from getpass import getpass
import hashlib
from http.cookies import SimpleCookie
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import re
import random
import secrets
from threading import RLock
from time import time
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parent
GAMES = {"tic-tac-toe", "slide-puzzle", "quiz"}
EMAIL = re.compile(r"[^\s@]+@[^\s@]+\.[^\s@]+")
SESSION_SECONDS = 8 * 3600
SEED_QUESTIONS = [
    ("Which language runs in a browser?", ["PHP", "JavaScript", "MySQL", "Python"], 1),
    ("What does HTML stand for?", ["HyperText Markup Language", "High Tech Modern Language", "Home Tool Markup Language", "Hyper Transfer Main Link"], 0),
    ("Which database does the PHP edition use?", ["SQLite", "MongoDB", "MySQL", "Oracle"], 2),
    ("Which CSS property changes text color?", ["background", "font-style", "padding", "color"], 3),
    ("What is 5 + 7?", ["10", "12", "13", "15"], 1),
]


class Problem(Exception):
    def __init__(self, message, status=400):
        super().__init__(message)
        self.status = status


def require(condition, message, status=400):
    if not condition:
        raise Problem(message, status)


def timestamp():
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def email_address(value):
    value = value.strip().lower()
    require(len(value) <= 120 and EMAIL.fullmatch(value), "Enter a valid email address.")
    return value


def password_hash(password, salt=None):
    salt = salt or secrets.token_hex(16)
    digest = hashlib.scrypt(password.encode(), salt=bytes.fromhex(salt), n=16384, r=8, p=1)
    return f"{salt}:{digest.hex()}"


def new_password(value):
    require(8 <= len(value) <= 128, "Use 8 to 128 characters for your password.")
    return password_hash(value)


def player_info(email, user):
    return {"email": email, **{key: user[key] for key in ("name", "bio", "role")}}


class Store:
    def __init__(self, path):
        self.path = path
        self.lock = RLock()
        self.data = json.loads(path.read_text(encoding="utf-8")) if path.exists() else {
            "users": {}, "results": [], "messages": []
        }
        self.data.setdefault("questions", [
            {"id": str(index), "question": question, "options": options, "correct": correct}
            for index, (question, options, correct) in enumerate(SEED_QUESTIONS, 1)
        ])
        self.sessions = {}
        self.reset_codes = {}

    def commit(self, **changes):
        next_data = {**self.data, **changes}
        self.path.parent.mkdir(parents=True, exist_ok=True)
        temp = self.path.with_suffix(".tmp")
        temp.write_text(json.dumps(next_data, ensure_ascii=False, indent=2), encoding="utf-8")
        temp.replace(self.path)
        self.data = next_data

    def create_user(self, email, name, password, role="player"):
        require(email not in self.data["users"], "An account already uses this email.", 409)
        require(1 <= len(name) <= 40, "Enter a display name of 1 to 40 characters.")
        user = {"name": name, "bio": "", "role": role, "password": new_password(password)}
        self.commit(users={**self.data["users"], email: user})

    def prune(self):
        now = time()
        self.sessions = {token: entry for token, entry in self.sessions.items() if entry[1] > now}
        self.reset_codes = {email: entry for email, entry in self.reset_codes.items() if entry[1] > now}


class Handler(SimpleHTTPRequestHandler):
    def do_GET(self):
        if urlsplit(self.path).path.startswith("/api/"):
            self.api()
        else:
            super().do_GET()

    def do_POST(self):
        self.api()

    def list_directory(self, path):
        self.send_error(404)
        return None

    def api(self):
        try:
            path = urlsplit(self.path)
            body = {}
            if self.command == "POST":
                origin = self.headers.get("Origin")
                require(origin is None or origin == f"http://{self.headers.get('Host')}", "Invalid origin.", 403)
                require(self.headers.get_content_type() == "application/json", "Use a JSON request.", 415)
                length = int(self.headers.get("Content-Length", 0))
                require(0 < length <= 8192, "Request must contain up to 8 KB of JSON.", 413)
                body = json.loads(self.rfile.read(length))
                require(isinstance(body, dict), "Expected a JSON object.")
            cookie = SimpleCookie(self.headers.get("Cookie", ""))
            token = cookie["gridline"].value if "gridline" in cookie else ""
            with self.server.store.lock:
                self.server.store.prune()
                data, session = self.route(path, body, token)
            self.respond(200, data, session)
        except Problem as error:
            self.respond(error.status, {"error": str(error)})
        except (ValueError, UnicodeError):
            self.respond(400, {"error": "Invalid request."})
        except OSError:
            self.respond(500, {"error": "The local server could not save the request."})

    def respond(self, status, body, session=None):
        content = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(content)))
        self.send_header("Cache-Control", "no-store")
        if session is not None:
            expiry = SESSION_SECONDS if session else 0
            self.send_header("Set-Cookie", f"gridline={session}; HttpOnly; SameSite=Strict; Path=/; Max-Age={expiry}")
        self.end_headers()
        self.wfile.write(content)

    def route(self, path, body, token):
        store = self.server.store
        users = store.data["users"]
        email, _ = store.sessions.get(token, ("", 0))
        user = users.get(email)
        route = (self.command, path.path)

        def value(key):
            item = body.get(key, "")
            require(isinstance(item, str), f"Expected text for {key}.")
            return item

        if route == ("GET", "/api/session"):
            return {"user": player_info(email, user) if user else None}, None
        if route == ("GET", "/api/questions"):
            questions = store.data["questions"]
            return {"questions": random.sample(questions, min(5, len(questions)))}, None
        if route == ("POST", "/api/register"):
            address = email_address(value("email"))
            store.create_user(address, value("name").strip(), value("password"))
            return {"message": "Account created. You can now log in."}, None
        if route == ("POST", "/api/login"):
            address = email_address(value("email"))
            password = value("password")
            account = users.get(address)
            require(account and len(password) <= 128 and secrets.compare_digest(
                password_hash(password, account["password"].split(":")[0]), account["password"]
            ), "Email or password is incorrect.", 401)
            store.sessions.pop(token, None)
            new_token = secrets.token_urlsafe(32)
            store.sessions[new_token] = (address, time() + SESSION_SECONDS)
            return {"message": "Logged in."}, new_token
        if route == ("POST", "/api/logout"):
            store.sessions.pop(token, None)
            return {"message": "Logged out."}, ""
        if route == ("POST", "/api/forgot"):
            address = email_address(value("email"))
            if address in users:
                code = secrets.token_urlsafe(18)
                store.reset_codes[address] = (code, time() + 600)
                print(f"Password reset for {address}: {code}", flush=True)
            return {"message": "If the account exists, the Python terminal shows a code valid for 10 minutes."}, None
        if route == ("POST", "/api/reset"):
            address = email_address(value("email"))
            code, _ = store.reset_codes.get(address, ("", 0))
            supplied = value("code").strip().encode()
            require(code and secrets.compare_digest(code.encode(), supplied), "The reset code is invalid or expired.")
            account = {**users[address], "password": new_password(value("password"))}
            store.commit(users={**users, address: account})
            del store.reset_codes[address]
            store.sessions = {key: item for key, item in store.sessions.items() if item[0] != address}
            return {"message": "Password updated. You can now log in."}, ""
        if route == ("POST", "/api/contact"):
            address = email_address(value("email"))
            message = value("message").strip()
            require(1 <= len(message) <= 1000, "Enter a message of 1 to 1000 characters.")
            store.commit(messages=[*store.data["messages"], {"email": address, "message": message, "played": timestamp()}])
            return {"message": "Message sent to the project dashboard."}, None
        if route == ("GET", "/api/leaderboard"):
            game = parse_qs(path.query).get("game", [""])[0]
            require(game in GAMES, "Choose a valid game.")
            scores = {}
            for result in store.data["results"]:
                if result["game"] == game:
                    scores.setdefault(result["email"], []).append(result["score"])
            aggregate = {"tic-tac-toe": sum, "slide-puzzle": min, "quiz": max}[game]
            rankings = [{"name": users[address]["name"], "score": aggregate(points)} for address, points in scores.items()]
            rankings.sort(key=lambda row: row["score"], reverse=game != "slide-puzzle")
            return {"rows": rankings[:20]}, None
        if path.path == "/api/admin" or path.path.startswith("/api/admin/"):
            require(user, "Log in to open the admin dashboard.", 401)
            require(user["role"] == "admin", "An administrator account is required.", 403)
        if route == ("GET", "/api/admin"):
            return {
                "players": [player_info(address, account) for address, account in users.items()],
                "results": len(store.data["results"]),
                "messages": list(reversed(store.data["messages"])),
                "questions": store.data["questions"],
            }, None
        if route == ("POST", "/api/admin/add-question"):
            question = value("question").strip()
            options = [value(letter).strip() for letter in "abcd"]
            correct = value("correct")
            require(1 <= len(question) <= 500 and all(1 <= len(option) <= 255 for option in options)
                    and correct in {"0", "1", "2", "3"}, "Enter a question, four answers, and the correct option.")
            item = {"id": secrets.token_hex(12), "question": question, "options": options, "correct": int(correct)}
            store.commit(questions=[*store.data["questions"], item])
            return {"message": "Question added."}, None
        if route == ("POST", "/api/admin/delete-question"):
            question_id = value("id")
            store.commit(questions=[item for item in store.data["questions"] if item["id"] != question_id])
            return {"message": "Question deleted."}, None
        if route == ("POST", "/api/profile"):
            require(user, "Log in to edit your profile.", 401)
            name, bio = value("name").strip(), value("bio").strip()
            require(1 <= len(name) <= 40 and len(bio) <= 240, "Enter a name of 1 to 40 characters and a bio of up to 240 characters.")
            store.commit(users={**users, email: {**user, "name": name, "bio": bio}})
            return {"message": "Profile saved."}, None
        if route == ("GET", "/api/history"):
            require(user, "Log in to see your history.", 401)
            return {"rows": [row for row in reversed(store.data["results"]) if row["email"] == email]}, None
        if route == ("POST", "/api/results"):
            require(user, "Log in to save your result.", 401)
            game, outcome = value("game"), value("outcome")
            score = body.get("score")
            round_id = value("roundId")
            require(re.fullmatch(r"[0-9a-f-]{36}", round_id) and type(score) is int, "Invalid game result.")
            valid = {
                "tic-tac-toe": (score == 1 and outcome == "X wins") or (score == 0 and outcome in {"Draw", "O wins"}),
                "slide-puzzle": 1 <= score <= 10000 and outcome == "Solved",
                "quiz": 0 <= score <= 5 and outcome == "Completed",
            }
            require(valid.get(game), "Invalid game result.")
            results = store.data["results"]
            if not any(row["email"] == email and row["roundId"] == round_id for row in results):
                result = {"email": email, "game": game, "outcome": outcome, "score": score, "roundId": round_id, "played": timestamp()}
                store.commit(results=[*results, result])
            return {"message": "Result saved."}, None
        raise Problem("Page or action was not found.", 404)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8000)
    parser.add_argument("--data", type=Path, default=ROOT / "data" / "arcade.json")
    parser.add_argument("--create-admin", metavar="EMAIL")
    args = parser.parse_args()
    store = Store(args.data)
    if args.create_admin:
        try:
            store.create_user(email_address(args.create_admin), "Arcade Admin", getpass("Admin password (8+ characters): "), "admin")
        except Problem as error:
            parser.error(str(error))
        print("Administrator created.")
        return
    handler = partial(Handler, directory=str(ROOT / "public"))
    with ThreadingHTTPServer(("127.0.0.1", args.port), handler) as server:
        server.store = store
        print(f"Gridline Arcade: http://127.0.0.1:{server.server_port}/", flush=True)
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass


if __name__ == "__main__":
    main()

