<?php
// auth.php

function getDb(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('sqlite:game.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tabelle erstellen, falls sie nicht existiert
        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT
            );
        SQL);
    }
    return $db;
}

function authFlow(): int {
    $db = getDb();

    while (true) {
        echo "\n_ÿ💻 Auth CLI (SQLite)\n----------------------\n";
        echo "1) Login\n2) Registrierung\n3) Beenden\n";
        $choice = readline("Bitte wählen (1-3): ");

        switch ($choice) {
            case '1':
                echo "\n🔐 Login\n";
                $username = readline("Benutzername: ");
                $password = readline("Passwort: ");

                $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    echo "✅ Willkommen zurück, $username!\n";
                    return (int) $user['id'];
                } else {
                    echo "❌ Login fehlgeschlagen.\n";
                }
                break;

            case '2':
                echo "\n🆕 Registrierung\n";
                $username = readline("Benutzername: ");
                $password = readline("Passwort: ");
                $hash = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->execute([$username, $hash]);
                    echo "✅ Benutzer '$username' wurde registriert.\n";
                    return (int)$db->lastInsertId();
                } catch (PDOException $e) {
                    echo "❌ Registrierung fehlgeschlagen: Benutzername existiert bereits.\n";
                }
                break;

            case '3':
                exit("👋 Auf Wiedersehen!\n");

            default:
                echo "Ungültige Eingabe.\n";
        }
    }
}
