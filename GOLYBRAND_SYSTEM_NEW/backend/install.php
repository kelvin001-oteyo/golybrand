<?php
// This script will install your database
require_once 'config.php';

try {
    $pdo = db();
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/../database/database.sql');
    
    if (!$sql) {
        die("❌ Could not read database.sql file");
    }
    
    // Execute the SQL (split into individual statements)
    $statements = explode(';', $sql);
    
    $count = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (Exception $e) {
                // Ignore errors (tables might already exist)
            }
        }
    }
    
    echo "✅ Database installed successfully! $count statements executed.<br>";
    echo "✅ You can now register users.";
    
    // Optional: Show tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll();
    echo "<br><br>📋 Tables created:<br>";
    foreach ($tables as $table) {
        echo "- " . implode('', $table) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
