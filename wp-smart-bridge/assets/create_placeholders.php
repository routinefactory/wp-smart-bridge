<?php
/**
 * 플레이스홀더 이미지 생성 스크립트
 * 
 * 이 스크립트는 WordPress 플러그인용 아이콘과 배너 플레이스홀더를 생성합니다.
 * GD 라이브러리가 없는 환경에서도 작동합니다.
 */

// 최소한의 1x1 PNG 픽셀 (투명)
$transparent_png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');

// 디렉토리 경로
$dir = __DIR__;

$files = [
    'icon-128x128.png',
    'icon-256x256.png',
    'banner-772x250.png',
    'banner-1544x500.png',
];

foreach ($files as $filename) {
    $filepath = $dir . '/' . $filename;
    file_put_contents($filepath, $transparent_png);
    echo "Created: $filename (placeholder)\n";
}

echo "\nAll placeholder images created successfully!\n";
echo "Note: These are minimal PNG placeholders. Replace them with actual images before deployment.\n";
