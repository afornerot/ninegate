#!/usr/bin/env php
<?php
/**
 * Simple HTML to Markdown converter
 * Usage: php html2md.php <input.html >output.md
 */
$html = file_get_contents('php://input');

// Decode HTML entities
$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

// Remove <style> and <script> blocks
$html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
$html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);

// Headers
$html = preg_replace('#<h1[^>]*>(.*?)</h1>#is', "\n# \\1\n", $html);
$html = preg_replace('#<h2[^>]*>(.*?)</h2>#is', "\n## \\1\n", $html);
$html = preg_replace('#<h3[^>]*>(.*?)</h3>#is', "\n### \\1\n", $html);
$html = preg_replace('#<h4[^>]*>(.*?)</h4>#is', "\n#### \\1\n", $html);
$html = preg_replace('#<h5[^>]*>(.*?)</h5>#is', "\n##### \\1\n", $html);
$html = preg_replace('#<h6[^>]*>(.*?)</h6>#is', "\n###### \\1\n", $html);

// Bold and italic
$html = preg_replace('#<(strong|b)[^>]*>(.*?)</\1>#is', '**\\2**', $html);
$html = preg_replace('#<(em|i)[^>]*>(.*?)</\1>#is', '*\\2*', $html);

// Links
$html = preg_replace('#<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', '[\\2](\\1)', $html);

// Images
$html = preg_replace('#<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*/?>#is', '![\\2](\\1)', $html);
$html = preg_replace('#<img[^>]*src="([^"]*)"[^>]*/?>#is', '![](#1)', $html);

// Blockquotes
$html = preg_replace('#<blockquote[^>]*>(.*?)</blockquote>#is', "\n> \\1\n", $html);

// Code blocks
$html = preg_replace('#<pre[^>]*><code[^>]*>(.*?)</code></pre>#is', "\n```\n\\1\n```\n", $html);
$html = preg_replace('#<pre[^>]*>(.*?)</pre>#is', "\n```\n\\1\n```\n", $html);
$html = preg_replace('#<code[^>]*>(.*?)</code>#is', '`\\1`', $html);

// Lists
$html = preg_replace('#<ul[^>]*>\s*#is', "\n", $html);
$html = preg_replace('#</ul>\s*#is', "\n", $html);
$html = preg_replace('#<ol[^>]*>\s*#is', "\n", $html);
$html = preg_replace('#</ol>\s*#is', "\n", $html);
$html = preg_replace('#<li[^>]*>(.*?)</li>#is', "- \\1\n", $html);

// Paragraphs and line breaks
$html = preg_replace('#<br\s*/?>#is', "\n", $html);
$html = preg_replace('#<p[^>]*>(.*?)</p>#is', "\n\\1\n", $html);
$html = preg_replace('#<div[^>]*>(.*?)</div>#is', "\n\\1\n", $html);
$html = preg_replace('#<span[^>]*>(.*?)</span>#is', '\\1', $html);

// Horizontal rule
$html = preg_replace('#<hr[^>]*/?>#is', "\n---\n", $html);

// Tables (basic)
$html = preg_replace('#<table[^>]*>#is', "\n", $html);
$html = preg_replace('#</table>#is', "\n", $html);
$html = preg_replace('#<tr[^>]*>#is', "| ", $html);
$html = preg_replace('#</tr>#is', "\n", $html);
$html = preg_replace('#<t[dh][^>]*>(.*?)</t[dh]>#is', "\\1 | ", $html);

// Remove remaining HTML tags
$html = preg_replace('#<[^>]+>#', '', $html);

// Decode remaining entities
$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

// Clean up whitespace
$html = preg_replace('#\n{3,}#', "\n\n", $html);
$html = trim($html);

echo $html;
