<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

echo "<pre>";
try {
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         ORDER BY p.id DESC
         LIMIT 10");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Products fetched: " . count($res) . "\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
echo "</pre>";
