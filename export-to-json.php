<?php
// Database connection
$host = '127.0.0.1';
$port = 8889;
$dbname = 'fairytaleproject';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n";
    
    // Define images directory path
    $imagesDir = __DIR__ . '/images/';
    
    // Fetch all participants with their region names
    $query = "
        SELECT 
            fm.id,
            fm.participant_number,
            fm.participant_gender,
            fm.participant_year,
            fm.themes,
            r.Participant_Region as region_name
        FROM fairytale_main fm
        LEFT JOIN regions r ON fm.participant_region = r.id
        WHERE fm.active = 1
        ORDER BY fm.participant_number
    ";
    
    $stmt = $pdo->query($query);
    $participants = [];
    
    // Fetch all themes for mapping
    $themeQuery = "SELECT id, theme_english FROM themes";
    $themeStmt = $pdo->query($themeQuery);
    $themeMap = [];
    while ($theme = $themeStmt->fetch(PDO::FETCH_ASSOC)) {
        $themeMap[$theme['id']] = $theme['theme_english'];
    }
    
    echo "Processing " . $stmt->rowCount() . " participants...\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Calculate generation from year
        $year = intval($row['participant_year']);
        $decade = floor($year / 10) * 10;
        $generation = $decade . "s";
        
        // Map region - all Chinese regions become "East Asia"
        $region = "East Asia";
        
        // Process themes - convert comma-separated IDs to theme names
        $themeIds = array_filter(explode(',', $row['themes']));
        $themeNames = [];
        foreach ($themeIds as $themeId) {
            $themeId = trim($themeId);
            if (isset($themeMap[$themeId])) {
                $themeNames[] = $themeMap[$themeId];
            }
        }
        
        // Determine gender
        $gender = ucfirst(strtolower($row['participant_gender']));
        
        // For now, set language as Chinese (we can refine this later if needed)
        $language = ["Chinese"];
        
        // Find photo for this participant
        $participantId = str_pad($row['participant_number'], 4, '0', STR_PAD_LEFT);
        $photo = null;
        
        // Check for main portrait photo
        $mainPhoto = $participantId . '_2007_portrait.jpg';
        if (file_exists($imagesDir . $mainPhoto)) {
            $photo = $mainPhoto;
        } else {
            // Check for variant photos (e.g., -1.jpg, -2.jpg)
            $variantPhoto = $participantId . '_2007_portrait-1.jpg';
            if (file_exists($imagesDir . $variantPhoto)) {
                $photo = $variantPhoto;
            }
        }
        
        // Build participant object matching your current format
        $participant = [
            "id" => $participantId,
            "gender" => $gender,
            "year" => $year,
            "generation" => $generation,
            "region" => $region,
            "language" => $language,
            "themes" => $themeNames,
            "photo" => $photo  // Add photo filename or null
        ];
        
        $participants[] = $participant;
    }
    
    echo "Processed " . count($participants) . " participants.\n";
    
    // Count how many have photos
    $withPhotos = count(array_filter($participants, function($p) { return $p['photo'] !== null; }));
    echo "Participants with photos: $withPhotos\n";
    
    // Write to JSON file
    $jsonFile = 'fairytale-data.json';
    $jsonData = json_encode($participants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($jsonFile, $jsonData)) {
        echo "Successfully exported to $jsonFile\n";
        echo "File size: " . round(filesize($jsonFile) / 1024, 2) . " KB\n";
    } else {
        echo "Error writing to file!\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
