<?php
/**
 * .mo 번역 파일 생성 스크립트
 * 
 * 이 스크립트는 .pot 파일을 기반으로 기본 영어 번역 파일(.mo)을 생성합니다.
 */

// .pot 파일 경로
$pot_file = __DIR__ . '/sb.pot';

// .mo 파일 경로
$mo_file = __DIR__ . '/wp-smart-bridge-en_US.mo';

// .pot 파일 읽기
$pot_content = file_get_contents($pot_file);
if ($pot_content === false) {
    die("Failed to read .pot file: $pot_file\n");
}

// .mo 파일 헤더 생성
$mo_content = '';
$mo_content .= pack('V', 0x950412de); // Magic number (little endian)
$mo_content .= pack('V', 0); // Format revision
$mo_content .= pack('V', 0); // Number of strings
$mo_content .= pack('V', 28); // Offset of table with original strings
$mo_content .= pack('V', 28); // Offset of table with translation strings
$mo_content .= pack('V', 0); // Size of hashing table
$mo_content .= pack('V', 0); // Offset of hashing table

// .mo 파일 저장
file_put_contents($mo_file, $mo_content);

echo "Created: wp-smart-bridge-en_US.mo (empty translation file)\n";
echo "Note: This is a minimal .mo file. Use gettext tools like msgfmt to create proper translations.\n";
