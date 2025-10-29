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
    
    echo "Connected to database successfully!\n\n";
    
    // Define images directory path
    $imagesDir = __DIR__ . '/images/';
    
    // Fetch all media files for photo lookup
    echo "Loading media files from directus_media...\n";
    $mediaQuery = "SELECT id, file_name, type FROM directus_media";
    $mediaStmt = $pdo->query($mediaQuery);
    $mediaMap = [];
    while ($media = $mediaStmt->fetch(PDO::FETCH_ASSOC)) {
        $mediaMap[$media['id']] = [
            'file_name' => $media['file_name'],
            'type' => $media['type']
        ];
    }
    echo "Loaded " . count($mediaMap) . " media files\n\n";
    
    // Fetch all participants with ALL photo columns
    $query = "
        SELECT 
            fm.id,
            fm.participant_number,
            fm.participant_gender,
            fm.participant_year,
            fm.themes,
            fm.participant_photo,
            fm.participant_pictures,
            fm.participant_media,
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
    
    $multiPhotoCount = 0;
    $maxPhotos = 0;
    $multiPhotoExamples = [];
    $invalidYearCount = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
     	
        // Calculate generation from year - HANDLE INVALID YEARS GRACEFULLY
        $year = intval($row['participant_year']);
        
        $generation = "";
        if ($year >= 1900 && $year <= 2100) {
            $decade = floor($year / 10) * 10;
            $generation = $decade . "s";
        } else {
            $invalidYearCount++;
        }
        
        // Keep the actual province/region name from the database (or empty if null)
        $region = $row['region_name'] ?: "";

        // Process themes
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
        
        // For now, set language as Chinese
        $language = ["Chinese"];
        
        // ===== COLLECT ALL PHOTOS FROM THREE COLUMNS =====
        $participantId = str_pad($row['participant_number'], 4, '0', STR_PAD_LEFT);
        $allPhotos = [];
        
        // 1. Main portrait (participant_photo)
        $photoIdsRaw = trim($row['participant_photo'], ',');
        $photoIds = array_filter(explode(',', $photoIdsRaw));
        foreach ($photoIds as $photoId) {
            $photoId = trim($photoId);
            if (isset($mediaMap[$photoId]) && $mediaMap[$photoId]['type'] == 'image') {
                $filename = $mediaMap[$photoId]['file_name'];
                // Try with .jpg extension if base filename doesn't exist
                if (file_exists($imagesDir . $filename)) {
                    $allPhotos[] = $filename;
                } elseif (file_exists($imagesDir . $filename . '.jpg')) {
                    $allPhotos[] = $filename . '.jpg';
                }
            }
        }
        
        // 2. Additional pictures (participant_pictures)
        $picturesIdsRaw = trim($row['participant_pictures'], ',');
        $picturesIds = array_filter(explode(',', $picturesIdsRaw));
        foreach ($picturesIds as $pictureId) {
            $pictureId = trim($pictureId);
            if (isset($mediaMap[$pictureId]) && $mediaMap[$pictureId]['type'] == 'image') {
                $filename = $mediaMap[$pictureId]['file_name'];
                // Try with .jpg extension if base filename doesn't exist
                if (file_exists($imagesDir . $filename)) {
                    $allPhotos[] = $filename;
                } elseif (file_exists($imagesDir . $filename . '.jpg')) {
                    $allPhotos[] = $filename . '.jpg';
                }
            }
        }
        
        // 3. Media files (participant_media) - only images
        $mediaIdsRaw = trim($row['participant_media'], ',');
        $mediaIds = array_filter(explode(',', $mediaIdsRaw));
        foreach ($mediaIds as $mediaId) {
            $mediaId = trim($mediaId);
            if (isset($mediaMap[$mediaId]) && $mediaMap[$mediaId]['type'] == 'image') {
                $filename = $mediaMap[$mediaId]['file_name'];
                // Try with .jpg extension if base filename doesn't exist
                if (file_exists($imagesDir . $filename)) {
                    $allPhotos[] = $filename;
                } elseif (file_exists($imagesDir . $filename . '.jpg')) {
                    $allPhotos[] = $filename . '.jpg';
                }
            }
        }
        
        // Remove duplicates
        $allPhotos = array_unique($allPhotos);
        $allPhotos = array_values($allPhotos); // Re-index array
        
        // If no photos found, try filesystem fallback
        if (empty($allPhotos)) {
            $mainPhoto = $participantId . '_2007_portrait.jpg';
            if (file_exists($imagesDir . $mainPhoto)) {
                $allPhotos[] = $mainPhoto;
            } else {
                $variantPhoto = $participantId . '_2007_portrait-1.jpg';
                if (file_exists($imagesDir . $variantPhoto)) {
                    $allPhotos[] = $variantPhoto;
                }
            }
        }
        
        // Track statistics
        if (count($allPhotos) > 1) {
            $multiPhotoCount++;
            if (count($multiPhotoExamples) < 10) {
                $multiPhotoExamples[] = [
                    'id' => $participantId,
                    'count' => count($allPhotos),
                    'photos' => $allPhotos
                ];
            }
        }
        if (count($allPhotos) > $maxPhotos) {
            $maxPhotos = count($allPhotos);
        }
        
        // Build participant object - INCLUDE EVERYONE
        $participant = [
            "id" => $participantId,
            "gender" => $gender,
            "year" => $year,
            "generation" => $generation,
            "region" => $region,
            "language" => $language,
            "themes" => $themeNames,
            "photos" => $allPhotos,  // ALL photos from all three columns!
            "photo_count" => count($allPhotos)
        ];
        
        $participants[] = $participant;
    }
    
    echo "\n=== EXPORT SUMMARY ===\n";
    echo "Total participants: " . count($participants) . "\n";
    echo "Participants with photos: " . count(array_filter($participants, function($p) { return count($p['photos']) > 0; })) . "\n";
    echo "Participants with MULTIPLE photos: $multiPhotoCount\n";
    echo "Maximum photos for one participant: $maxPhotos\n";
    echo "Participants with invalid years (included anyway): $invalidYearCount\n\n";
    
    // Show examples
    if (!empty($multiPhotoExamples)) {
        echo "=== EXAMPLES OF PARTICIPANTS WITH MULTIPLE PHOTOS ===\n";
        foreach ($multiPhotoExamples as $example) {
            echo "ID {$example['id']}: {$example['count']} photos\n";
            foreach ($example['photos'] as $photo) {
                echo "  - $photo\n";
            }
        }
        echo "\n";
    }
    
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