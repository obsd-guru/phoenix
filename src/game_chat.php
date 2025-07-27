<?php
require_once "db.php";

function initChat(): void {
    $db = getDb();
    $db->exec("
    CREATE TABLE IF NOT EXISTS chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
    ");
}

function chatAnzeigen(int $userId): void {
    $db = getDb();
    $msgs = $db->query("
        SELECT c.message, u.username, c.created_at 
        FROM chat_messages c 
        JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "💬 Letzte Chat-Nachrichten:\n";
    foreach (

