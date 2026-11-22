<?php
require __DIR__ . '/layout.php';
require_admin();

$tabs = [
    'overview'   => 'Overview',
    'settings'   => 'Settings',
    'games'      => 'Games',
    'categories' => 'Categories',
    'questions'  => 'Questions',
    'users'      => 'Users',
    'messages'   => 'Messages',
    'logs'       => 'Login logs',
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}
$me = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $back = 'admin.php?tab=' . $tab;

    // ---- admin_settings
    if ($action === 'save_settings') {
        $rows = db()->query('SELECT setting_key FROM admin_settings')->fetchAll(PDO::FETCH_COLUMN);
        $numeric = ['session_timeout_minutes' => [1, 1440], 'quiz_question_count' => [1, 20],
                    'max_login_attempts' => [1, 20], 'lockout_minutes' => [1, 1440], 'leaderboard_size' => [1, 100]];
        $update = db()->prepare('UPDATE admin_settings SET setting_value=? WHERE setting_key=?');
        $problem = null;
        foreach ($rows as $key) {
            if (!isset($_POST['s'][$key])) {
                continue;
            }
            $value = trim((string) $_POST['s'][$key]);
            if (isset($numeric[$key])) {
                $n = filter_var($value, FILTER_VALIDATE_INT);
                if ($n === false || $n < $numeric[$key][0] || $n > $numeric[$key][1]) {
                    $problem = "$key must be a whole number between {$numeric[$key][0]} and {$numeric[$key][1]}.";
                    continue;
                }
                $value = (string) $n;
            } elseif ($key === 'registration_open') {
                $value = $value === '1' ? '1' : '0';
            } elseif (strlen($value) > 255) {
                $problem = "$key is too long.";
                continue;
            }
            $update->execute([$value, $key]);
        }
        flash($problem ?? 'Settings saved.');
        redirect($back);
    }

    // ---- games
    if ($action === 'update_game') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($title !== '' && strlen($title) <= 80 && $desc !== '' && strlen($desc) <= 255 && $order !== false) {
            db()->prepare('UPDATE games SET title=?, description=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$title, $desc, $order, empty($_POST['is_active']) ? 0 : 1, $id]);
            flash('Game updated.');
        } else {
            flash('Please give the game a title, a description and a numeric order.');
        }
        redirect($back);
    }

    // ---- question_categories
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '' && strlen($name) <= 60 && strlen($desc) <= 255) {
            try {
                db()->prepare('INSERT INTO question_categories(name,description,created_by) VALUES(?,?,?)')
                    ->execute([$name, $desc, actor()]);
                flash('Category added.');
            } catch (PDOException $ex) {
                flash('That category already exists.');
            }
        }
        redirect($back);
    }
    if ($action === 'delete_category') {
        db()->prepare('DELETE FROM question_categories WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Category deleted. Its questions are kept without a category.');
        redirect($back);
    }

    // ---- questions
    if ($action === 'add_question') {
        $q = trim($_POST['question'] ?? '');
        $opts = array_map('trim', [$_POST['a'] ?? '', $_POST['b'] ?? '', $_POST['c'] ?? '', $_POST['d'] ?? '']);
        $correct = filter_var($_POST['correct'] ?? '', FILTER_VALIDATE_INT);
        $category = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        if ($q !== '' && strlen($q) <= 500 && count(array_filter($opts)) === 4 && $correct !== false && $correct >= 0 && $correct <= 3) {
            db()->prepare('INSERT INTO questions(category_id,question,option_a,option_b,option_c,option_d,correct_index,created_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute(array_merge([$category === false ? null : $category, $q], $opts, [$correct, actor()]));
            flash('Question added.');
        } else {
            flash('Please fill in the question, all four options and the correct answer.');
        }
        redirect($back);
    }
    if ($action === 'delete_question') {
        db()->prepare('DELETE FROM questions WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Question deleted.');
        redirect($back);
    }

    // ---- users
    if ($action === 'update_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $status = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
        if ($id === (int) $me['id']) {
            flash('You cannot change your own role or status.');
        } else {
            db()->prepare('UPDATE users SET role=?, status=? WHERE id=?')->execute([$role, $status, $id]);
            flash('User updated.');
        }
        redirect($back);
    }

    // ---- contact_messages
    if ($action === 'mark_message') {
        db()->prepare("UPDATE contact_messages SET status = IF(status='new','read','new') WHERE id=?")
            ->execute([(int) ($_POST['id'] ?? 0)]);
        redirect($back);
    }
    if ($action === 'delete_message') {
        db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Message deleted.');
        redirect($back);
    }
}

// ------------------------------------------------------------------ data for the page
$counts = [];
foreach (['users', 'games', 'scores', 'question_categories', 'questions', 'contact_messages', 'login_logs', 'favorites', 'password_resets', 'admin_settings'] as $table) {
    $counts[$table] = db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}
$unread = db()->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();

function admin_form_open(string $action, string $extraClass = ''): void
{
    echo '<form method="post" class="' . e($extraClass) . '">';
    csrf_field();
    echo '<input type="hidden" name="action" value="' . e($action) . '">';
}

top('Admin Dashboard');
?>
<p class="filters">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="secondary" href="admin.php?tab=<?= e($key) ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?><?= $key === 'messages' && $unread ? ' (' . e($unread) . ')' : '' ?></a>
    <?php endforeach; ?>
</p>

<?php if ($tab === 'overview'): ?>
    <div class="grid2">
        <?php foreach ($counts as $table => $count): ?>
            <div class="stat"><strong><?= e($count) ?></strong><?= e(str_replace('_', ' ', ucfirst($table))) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="panel">
        <h2>Welcome, <?= e($me['username']) ?></h2>
        <p>Use the tabs above to manage every table in the database: settings, games, quiz categories and questions, users, contact messages and the login audit log.</p>
    </div>

<?php elseif ($tab === 'settings'):
    $settings = db()->query('SELECT setting_key, setting_value, description, updated_at FROM admin_settings ORDER BY id')->fetchAll(); ?>
    <div class="panel">
        <h2>Site settings</h2>
        <?php admin_form_open('save_settings', 'form'); ?>
            <?php foreach ($settings as $row): ?>
                <label><?= e($row['setting_key']) ?> <small>— <?= e($row['description']) ?></small>
                    <?php if ($row['setting_key'] === 'registration_open'): ?>
                        <select name="s[registration_open]">
                            <option value="1"<?= $row['setting_value'] === '1' ? ' selected' : '' ?>>Open</option>
                            <option value="0"<?= $row['setting_value'] !== '1' ? ' selected' : '' ?>>Closed</option>
                        </select>
                    <?php else: ?>
                        <input name="s[<?= e($row['setting_key']) ?>]" value="<?= e($row['setting_value']) ?>" maxlength="255">
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <button class="btn">Save settings</button>
        </form>
    </div>

<?php elseif ($tab === 'games'):
    $games = db()->query('SELECT id, slug, title, description, sort_order, is_active, created_by, created_at FROM games ORDER BY sort_order, id')->fetchAll(); ?>
    <div class="panel">
        <h2>Games</h2>
        <?php foreach ($games as $g): ?>
            <?php admin_form_open('update_game', 'form admin-row'); ?>
                <input type="hidden" name="id" value="<?= e($g['id']) ?>">
                <strong><?= e($g['slug']) ?></strong> <small>created by <?= e($g['created_by']) ?> on <?= e($g['created_at']) ?></small>
                <label>Title <input name="title" value="<?= e($g['title']) ?>" required maxlength="80"></label>
                <label>Description <input name="description" value="<?= e($g['description']) ?>" required maxlength="255"></label>
                <label>Order <input name="sort_order" value="<?= e($g['sort_order']) ?>" required></label>
                <label class="check"><input type="checkbox" name="is_active" value="1"<?= $g['is_active'] ? ' checked' : '' ?>> Visible to players</label>
                <button class="btn">Save game</button>
            </form>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'categories'):
    $cats = db()->query('SELECT c.id, c.name, c.description, c.created_by, c.created_at, COUNT(q.id) AS total
                         FROM question_categories c LEFT JOIN questions q ON q.category_id = c.id
                         GROUP BY c.id, c.name, c.description, c.created_by, c.created_at ORDER BY c.name')->fetchAll(); ?>
    <div class="panel">
        <h2>Quiz categories</h2>
        <?php admin_form_open('add_category', 'form'); ?>
            <label>Name <input name="name" required maxlength="60"></label>
            <label>Description <input name="description" maxlength="255"></label>
            <button class="btn">Add category</button>
        </form>
        <div class="table-wrap"><table>
            <tr><th>Name</th><th>Questions</th><th>Created by</th><th>Created</th><th>Action</th></tr>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td><td><?= e($c['total']) ?></td><td><?= e($c['created_by']) ?></td><td><?= e($c['created_at']) ?></td>
                    <td><?php admin_form_open('delete_category', 'inline-form'); ?>
                        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                        <button class="btn danger">Delete</button></form></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div>

<?php elseif ($tab === 'questions'):
    $cats = db()->query('SELECT id, name FROM question_categories ORDER BY name')->fetchAll();
    $questions = db()->query('SELECT q.id, q.question, q.created_by, c.name AS category
                              FROM questions q LEFT JOIN question_categories c ON c.id = q.category_id
                              ORDER BY q.id DESC LIMIT 50')->fetchAll(); ?>
    <div class="panel">
        <h2>Manage quiz questions</h2>
        <?php admin_form_open('add_question', 'form'); ?>
            <label>Category
                <select name="category_id">
                    <option value="">(none)</option>
                    <?php foreach ($cats as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Question <input name="question" required maxlength="500"></label>
            <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                <label>Option <?= strtoupper($letter) ?> <input name="<?= $letter ?>" required maxlength="255"></label>
            <?php endforeach; ?>
            <label>Correct option
                <select name="correct">
                    <option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option>
                </select>
            </label>
            <button class="btn">Add question</button>
        </form>
        <div class="table-wrap"><table>
            <tr><th>Question</th><th>Category</th><th>Created by</th><th>Action</th></tr>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?= e($q['question']) ?></td><td><?= e($q['category'] ?? '—') ?></td><td><?= e($q['created_by']) ?></td>
                    <td><?php admin_form_open('delete_question', 'inline-form'); ?>
                        <input type="hidden" name="id" value="<?= e($q['id']) ?>">
                        <button class="btn danger">Delete</button></form></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div>

<?php elseif ($tab === 'users'):
    $users = db()->query('SELECT id, username, email, role, status, last_login_at, created_by, created_at FROM users ORDER BY id DESC LIMIT 100')->fetchAll(); ?>
    <div class="panel">
        <h2>Users</h2>
        <div class="table-wrap"><table>
            <tr><th>Username</th><th>Email</th><th>Last login</th><th>Created by</th><th>Role / status</th></tr>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td>
                    <td><?= e($u['last_login_at'] ?? 'never') ?></td><td><?= e($u['created_by']) ?></td>
                    <td>
                        <?php if ((int) $u['id'] === (int) $me['id']): ?>
                            <?= e($u['role']) ?> / <?= e($u['status']) ?> (you)
                        <?php else: ?>
                            <?php admin_form_open('update_user', 'inline-form'); ?>
                                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                <select name="role">
                                    <option value="user"<?= $u['role'] === 'user' ? ' selected' : '' ?>>user</option>
                                    <option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>>admin</option>
                                </select>
                                <select name="status">
                                    <option value="active"<?= $u['status'] === 'active' ? ' selected' : '' ?>>active</option>
                                    <option value="suspended"<?= $u['status'] === 'suspended' ? ' selected' : '' ?>>suspended</option>
                                </select>
                                <button class="btn">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div>

<?php elseif ($tab === 'messages'):
    $messages = db()->query('SELECT id, name, email, message, status, created_by, created_at FROM contact_messages ORDER BY id DESC LIMIT 50')->fetchAll(); ?>
    <div class="panel">
        <h2>Contact messages</h2>
        <?php if (!$messages): ?><p>No messages yet.</p><?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <div class="message-card">
                <p><strong><?= e($m['name']) ?></strong> &lt;<?= e($m['email']) ?>&gt; · <?= e($m['created_at']) ?> · sent by <?= e($m['created_by']) ?> · <em><?= e($m['status']) ?></em></p>
                <p><?= nl2br(e($m['message'])) ?></p>
                <?php admin_form_open('mark_message', 'inline-form'); ?>
                    <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                    <button class="btn"><?= $m['status'] === 'new' ? 'Mark as read' : 'Mark as new' ?></button></form>
                <?php admin_form_open('delete_message', 'inline-form'); ?>
                    <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                    <button class="btn danger">Delete</button></form>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'logs'):
    $logs = db()->query('SELECT l.event, l.email_attempted, l.ip_address, l.created_by, l.created_at, u.username
                         FROM login_logs l LEFT JOIN users u ON u.id = l.user_id
                         ORDER BY l.id DESC LIMIT 100')->fetchAll(); ?>
    <div class="panel">
        <h2>Login and session log (latest 100)</h2>
        <div class="table-wrap"><table>
            <tr><th>When</th><th>Event</th><th>User</th><th>Email tried</th><th>IP</th></tr>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= e($l['created_at']) ?></td><td><?= e(str_replace('_', ' ', $l['event'])) ?></td>
                    <td><?= e($l['username'] ?? '—') ?></td><td><?= e($l['email_attempted'] ?: '—') ?></td><td><?= e($l['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
<?php endif; ?>
<?php bottom(); ?>
