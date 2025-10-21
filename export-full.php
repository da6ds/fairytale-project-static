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
    
    $sql = "
        SELECT 
            m.id,
            m.participant_number,
            m.participant_name,
            m.participant_photo,
            m.participant_region,
            m.participant_gender,
            m.participant_year,
            m.themes,
            e.bio as english_bio,
            c.bio as chinese_bio,
            g.bio as german_bio
        FROM fairytale_main m
        LEFT JOIN fairytale_english e ON m.id = e.id
        LEFT JOIN fairytale_chinese c ON m.id = c.id
        LEFT JOIN fairytale_german g ON m.id = g.id
        WHERE m.active = 1
        ORDER BY m.participant_number
    ";
    
    $stmt = $pdo->query($sql);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($entries) . " entries\n";
    
    $regions_sql = "SELECT id, Participant_Region FROM regions";
    $regions_stmt = $pdo->query($regions_sql);
    $regions = [];
    while ($row = $regions_stmt->fetch(PDO::FETCH_ASSOC)) {
        $regions[$row['id']] = $row['Participant_Region'];
    }
    
    $themes_sql = "SELECT id, theme_english FROM themes";
    $themes_stmt = $pdo->query($themes_sql);
    $themes_lookup = [];
    while ($row = $themes_stmt->fetch(PDO::FETCH_ASSOC)) {
        $themes_lookup[$row['id']] = $row['theme_english'];
    }
    
    $output = [];
    foreach ($entries as $entry) {
        $theme_ids = array_filter(explode(',', trim($entry['themes'], ',')));
        $theme_names = [];
        foreach ($theme_ids as $theme_id) {
            if (isset($themes_lookup[$theme_id])) {
                $theme_names[] = $themes_lookup[$theme_id];
            }
        }
        
        $photo_ids = array_filter(explode(',', trim($entry['participant_photo'], ',')));
        
        $region = isset($regions[$entry['participant_region']]) ? 
            $regions[$entry['participant_region']] : '';
        
        $year = (int)$entry['participant_year'];
        $generation = '';
        if ($year >= 1900 && $year <= 2020) {
            $decade_start = floor($year / 10) * 10;
            $generation = $decade_start . 's';
        }
        
        $output[] = [
            'id' => $entry['participant_number'],
            'name' => $entry['participant_name'] ?: '',
            'gender' => ucfirst($entry['participant_gender']),
            'year' => $year,
            'generation' => $generation,
            'region' => $region,
            'themes' => $theme_names,
            'photos' => $photo_ids,
            'bio_english' => $entry['english_bio'] ?: '',
            'bio_chinese' => $entry['chinese_bio'] ?: '',
            'bio_german' => $entry['german_bio'] ?: ''
        ];
    }
    
    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('fairytale-data.json', $json);
    
    echo "Successfully exported " . count($output) . " entries!\n";
    echo "File size: " . round(filesize('fairytale-data.json') / 1024 / 1024, 2) . " MB\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
