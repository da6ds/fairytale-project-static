<?php
$host = '127.0.0.1';
$port = 8889;
$dbname = 'fairytaleproject';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database!\n";
    
    $sql = "SELECT m.id, m.participant_number, m.participant_gender, m.participant_year FROM fairytale_main m WHERE m.active = 1 ORDER BY m.participant_number LIMIT 5";
    $stmt = $pdo->query($sql);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($entries) . " entries\n";
    echo json_encode($entries, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
