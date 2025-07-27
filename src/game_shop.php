<?php
require_once "db.php";

function initShop(): void {
    $db = getDb();
    $db->exec("
    CREATE TABLE IF NOT EXISTS shop_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        price INTEGER,
        type TEXT -- z.B. Waffe, Rüstung, Trank
    );
    CREATE TABLE IF NOT EXISTS user_gold (
        user_id INTEGER PRIMARY KEY,
        gold INTEGER DEFAULT 100,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
    ");

    // Shop-Items hinzufügen, falls leer
    $count = $db->query("SELECT COUNT(*) FROM shop_items")->fetchColumn();
    if ($count == 0) {
        $items = [
            ['name' => 'Heiltrank', 'price' => 10, 'type' => 'Trank'],
            ['name' => 'Stärketrank', 'price' => 15, 'type' => 'Trank'],
            ['name' => 'Schwert', 'price' => 50, 'type' => 'Waffe'],
            ['name' => 'Lederrüstung', 'price' => 40, 'type' => 'Rüstung'],
        ];
        $stmt = $db->prepare("INSERT INTO shop_items (name, price, type) VALUES (:name, :price, :type)");
        foreach ($items as $item) {
            $stmt->execute($item);
        }
    }
}

// Zeige Shop-Inventar + Kaufoption
function shopInteraktion(int $userId): void {
    $db = getDb();
    $goldStmt = $db->prepare("SELECT gold FROM user_gold WHERE user_id = :uid");
    $goldStmt->execute([':uid' => $userId]);
    $gold = $goldStmt->fetchColumn();
    if ($gold === false) {
        // Neu anlegen mit Startgold
        $insert = $db->prepare("INSERT INTO user_gold (user_id, gold) VALUES (:uid, 100)");
        $insert->execute([':uid' => $userId]);
        $gold = 100;
    }
    echo "💰 Du hast $gold Gold.\n";

    $items = $db->query("SELECT * FROM shop_items")->fetchAll(PDO::FETCH_ASSOC);
    echo "🛒 Händler bietet folgende Items an:\n";
    foreach ($items as $item) {
        echo "- {$item['name']} ({$item['type']}) für {$item['price']} Gold\n";
    }

    $wahl = readline("Möchtest du etwas kaufen? (Item-Name oder 'nein'): ");
    if (strtolower($wahl) === 'nein') {
        echo "🛍️ Einkauf abgebrochen.\n";
        return;
    }

    // Prüfe Item und Preis
    $stmt = $db->prepare("SELECT * FROM shop_items WHERE name = :name");
    $stmt->execute([':name' => $wahl]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        echo "❌ Item '$wahl' gibt es nicht.\n";
        return;
    }
    if ($gold < $item['price']) {
        echo "❌ Du hast nicht genug Gold.\n";
        return;
    }

    // Kauf durchführen
    $newGold = $gold - $item['price'];
    $update = $db->prepare("UPDATE user_gold SET gold = :gold WHERE user_id = :uid");
    $update->execute([':gold' => $newGold, ':uid' => $userId]);
    addItem($userId, $item['name'], 1);
    echo "✅ Du hast '{$item['name']}' gekauft!\n";
}

