<?php
/**
 * Clean HTML for blog article migration
 * Usage: php clean_html.php input.html output.html
 */
$html = file_get_contents($argv[1]);

// 1. Replace literal \n (from MySQL dump) with space
$html = str_replace('\n', ' ', $html);

// 2. Replace MySQL \' with '
$html = str_replace("\\'", "''", $html);

// 3. Replace real newlines with nothing (joins split words)
$html = preg_replace('/\r?\n/', '', $html);

// 4. Collapse multiple spaces
$html = preg_replace('/\s+/', ' ', $html);

// 5. Decode HTML entities
$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

// 6. Strip unknown/custom tags (keep standard HTML)
$html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
$html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
$html = preg_replace('/<[^a-zA-Z\/!][^>]*>/', '', $html);

file_put_contents($argv[2], $html);
