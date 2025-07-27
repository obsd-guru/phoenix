<?php
require_once "db.php";

// Gegner-Tabelle anlegen
function initEnemies(): void {
    $db = getDb();
    $db->exec("
    CREATE TABLE IF NOT EXISTS enemies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        hp INTEGER,
        attack INTEGER,
        defense INTEGER
    );
    CREATE TABLE IF NOT EXISTS user_stats (
        user_id INTEGER PRIMARY KEY,
        hp INTEGER,
        max_hp INTEGER,
        attack INTEGER,
        defense INTEGER,
        current_room INTEGER,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
    ");

    // Gegner einfügen, falls leer
    $count = $db->query("SELECT COUNT(*) FROM enemies")->fetchColumn();
    if ($count == 0) {
        $enemies = [
            ['name' => 'Goblin', 'hp' => 30, 'attack' => 5, 'defense' => 2],
            ['name' => 'Ork', 'hp' => 50, 'attack' => 10, 'defense' => 5],
            ['name' => 'Drachenjunges', 'hp' => 100, 'attack' => 20, 'defense' => 10],
        ];
        $insert = $db->prepare("INSERT INTO enemies (name, hp, attack, defense) VALUES (:name, :hp, :atk, :def)");
        foreach ($enemies as $e) {
            $insert->execute([
                ':name' => $e['name'],
                ':hp' => $e['hp'],
                ':atk' => $e['attack'],
                ':def' => $e['defense']
            ]);
        }
    }
}

// Kampfsimulation Spieler gegen Gegner
function kaempfen(int $userId): void {
    $db = getDb();
    // Spielerwerte holen
    $stmt = $db->prepare("SELECT hp, max_hp, attack, defense FROM user_stats WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "❌ Keine Spielerwerte gefunden.\n";
        return;
    }

    // Gegner zufällig auswählen
    $enemy = $db->query("SELECT * FROM enemies ORDER BY RANDOM() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$enemy) {
        echo "❌ Kein Gegner vorhanden.\n";
        return;
    }

    echo "⚔️ Ein wildes {$enemy['name']} erscheint!\n";

    $userHp = (int)$user['hp'];
    $enemyHp = (int)$enemy['hp'];

    while ($userHp > 0 && $enemyHp > 0) {
        // Spieler schlägt zu
        $damageToEnemy = max(0, $user['attack'] - $enemy['defense']);
        $enemyHp -= $damageToEnemy;
        echo "Du verursachst $damageToEnemy Schaden an {$enemy['name']} (HP übrig: " . max(0, $enemyHp) . ")\n";
        if ($enemyHp <= 0) {
            echo "🎉 Du hast den {$enemy['name']} besiegt!\n";
            // Loot (z.B. Heiltrank)
            addItem($userId, 'Heiltrank', 1);
            break;
        }

        // Gegner schlägt zu
        $damageToUser = max(0, $enemy['attack'] - $user['defense']);
        $userHp -= $damageToUser;
        echo "{$enemy['name']} verursacht $damageToUser Schaden an dir (HP übrig: " . max(0, $userHp) . ")\n";
        if ($userHp <= 0) {
            echo "💀 Du wurdest besiegt...\n";
            // Spieler HP auf 1 setzen (kein Tod, nur Niederlage)
            $userHp = 1;
            break;
        }
    }

    // Spieler HP speichern
    $update = $db->prepare("UPDATE user_stats SET hp = :hp WHERE user_id = :uid");
    $update->execute([':hp' => $userHp, ':uid' => $userId]);
}

