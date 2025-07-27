<?php
const DB_FILE = 'users.sqlite';

// DB-Verbindung aufbauen
function getDb(): PDO {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabelle erstellen, falls nicht vorhanden
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    return $pdo;
}

// Benutzer registrieren
function signup(PDO $db): void {
    echo "\n📋 Registrierung\n";
    $username = readline("Benutzername: ");
    $password = readline("Passwort: ");

    // Prüfen ob Benutzername existiert
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo "❌ Benutzername bereits vergeben.\n";
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);

    echo "✅ Benutzer '$username' erfolgreich registriert.\n";
}

// Benutzer anmelden
function login(PDO $db): void {
    echo "\n🔐 Login\n";
    $username = readline("Benutzername: ");
    $password = readline("Passwort: ");

    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "❌ Benutzer nicht gefunden.\n";
        return;
    }

    if (password_verify($password, $row['password'])) {
        echo "✅ Willkommen zurück, $username!\n";
    } else {
        echo "❌ Falsches Passwort.\n";
    }
}

// Hauptmenü
function main(): void {
    $db = getDb();

    echo "🧑‍💻 Auth CLI (SQLite)\n";
    echo "----------------------\n";

    while (true) {
        echo "\n1) Login\n2) Registrierung\n3) Beenden\n";
        $choice = readline("Bitte wählen (1-3): ");

        switch ($choice) {
            case '1':
                login($db);
                break;
            case '2':
                signup($db);
                break;
            case '3':
                echo "👋 Auf Wiedersehen!\n";
                exit(0);
            default:
                echo "⚠️  Ungültige Eingabe. Bitte 1, 2 oder 3 wählen.\n";
        }
    }
}

if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "❌ Dieses Skript ist nur für die Kommandozeile gedacht.\n";
}

