<?php
require_once 'config.php';

try {
    $pdo = getDB();

    // Cek apakah cache masih fresh (< 5 menit) - Menambahkan image_url pada SELECT
    $stmt = $pdo->query("
        SELECT title, image_url, popularity AS volume, score AS sentiment, trending_score AS trending, genres, movement
        FROM trending_cache
        WHERE fetched_at > NOW() - INTERVAL 5 MINUTE
        ORDER BY trending_score DESC LIMIT 10
    ");
    $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($cached)) {
        foreach ($cached as &$c) {
            $c['genres'] = explode(',', $c['genres']);
        }
        echo json_encode(['status' => 'ok', 'source' => 'cache', 'data' => $cached]);
        exit();
    }

    // Cache kosong/expired → fetch dari AniList - Menambahkan coverImage ke query GraphQL
    $query = '{"query":"{ Page(page:1,perPage:10){ media(sort:TRENDING_DESC,type:ANIME,status:RELEASING){ title{romaji} coverImage{large} trending popularity averageScore genres } } }"}';
    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $query,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $media = $data['data']['Page']['media'] ?? [];

    if (empty($media)) {
        throw new Exception("Gagal mengambil data dari AniList API");
    }

    // Hapus cache lama, simpan yang baru - Menambahkan image_url ke INSERT INTO
    $pdo->exec("DELETE FROM trending_cache");
    $insert = $pdo->prepare("
        INSERT INTO trending_cache (title, image_url, popularity, score, trending_score, genres, movement)
        VALUES (:title, :image_url, :popularity, :score, :trending, :genres, :movement)
    ");

    $avgTrending = array_sum(array_column($media, 'trending')) / count($media);
    $result = [];

    foreach ($media as $item) {
        $movement = ($item['trending'] >= $avgTrending) ? '▲' : '▼';
        $genresArr = array_slice($item['genres'] ?? [], 0, 3);
        $genresStr = implode(',', $genresArr);
        $imgUrl = $item['coverImage']['large'] ?? '';
        
        $insert->execute([
            ':title'      => $item['title']['romaji'],
            ':image_url'  => $imgUrl,
            ':popularity' => $item['popularity'],
            ':score'      => $item['averageScore'] ?? 75,
            ':trending'   => $item['trending'],
            ':genres'     => $genresStr,
            ':movement'   => $movement,
        ]);
        
        $result[] = [
            'title'     => $item['title']['romaji'],
            'image_url' => $imgUrl,
            'volume'    => $item['popularity'],
            'sentiment' => $item['averageScore'] ?? 75,
            'trending'  => $item['trending'],
            'genres'    => $genresArr,
            'movement'  => $movement,
        ];
    }

    echo json_encode(['status' => 'ok', 'source' => 'api', 'data' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}