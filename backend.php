<?php
header('Content-Type: application/json');
session_start();

// Database setup
$db = new SQLite3('db.sqlite');

// Create tables if they don't exist
$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    project_name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    note_name TEXT NOT NULL,
    duration TEXT NOT NULL,
    position_x INTEGER NOT NULL,
    position_y INTEGER NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id)
)');

// Handle requests
$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare('SELECT id, password FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'signup') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password', $password, SQLITE3_TEXT);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
    }
} elseif ($action === 'create_project') {
    $user_id = $_SESSION['user_id'];
    $project_name = $_POST['project_name'];

    $stmt = $db->prepare('INSERT INTO projects (user_id, project_name) VALUES (:user_id, :project_name)');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':project_name', $project_name, SQLITE3_TEXT);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'project_id' => $db->lastInsertRowID()]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create project']);
    }
} elseif ($action === 'save_notes') {
    $project_id = $_POST['project_id'];
    $notes = json_decode($_POST['notes'], true);

    $db->exec('DELETE FROM notes WHERE project_id = ' . $project_id); // Clear old notes

    foreach ($notes as $note) {
        $stmt = $db->prepare('INSERT INTO notes (project_id, note_name, duration, position_x, position_y) VALUES (:project_id, :note_name, :duration, :position_x, :position_y)');
        $stmt->bindValue(':project_id', $project_id, SQLITE3_INTEGER);
        $stmt->bindValue(':note_name', $note.note_name, SQLITE3_TEXT);
        $stmt->bindValue(':duration', $note.duration, SQLITE3_TEXT);
        $stmt->bindValue(':position_x', $note.position_x, SQLITE3_INTEGER);
        $stmt->bindValue(':position_y', $note.position_y, SQLITE3_INTEGER);
        $stmt->execute();
    }

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
