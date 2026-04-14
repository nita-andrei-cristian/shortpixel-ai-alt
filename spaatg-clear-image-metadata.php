<?php
declare(strict_types=1);

// One-shot maintenance endpoint for local development only.
// Usage:
//   http://localhost:8080/wp-content/plugins/shortpixel-ai-alt-text/spaatg-clear-image-metadata.php?token=b6a7be257a6f34de907a5c9033d3946b684e1b6221a55eb3

$allowed_token = 'b6a7be257a6f34de907a5c9033d3946b684e1b6221a55eb3';
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local_request = false;

if (in_array($remote_addr, ['127.0.0.1', '::1'], true)) {
	$is_local_request = true;
} elseif (filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
	$long_ip = ip2long($remote_addr);
	$private_ranges = [
		['10.0.0.0', '10.255.255.255'],
		['172.16.0.0', '172.31.255.255'],
		['192.168.0.0', '192.168.255.255'],
	];

	foreach ($private_ranges as [$start, $end]) {
		if ($long_ip >= ip2long($start) && $long_ip <= ip2long($end)) {
			$is_local_request = true;
			break;
		}
	}
}

if (! $is_local_request) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'error' => 'local_only']);
	exit;
}

if (($_GET['token'] ?? '') !== $allowed_token) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'error' => 'forbidden']);
	exit;
}

require dirname(__DIR__, 3) . '/wp-load.php';

if (! defined('ABSPATH')) {
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'error' => 'wordpress_not_loaded']);
	exit;
}

global $wpdb;

$posts_table = $wpdb->posts;
$postmeta_table = $wpdb->postmeta;
$ai_table = $wpdb->prefix . 'shortpixel_aipostmeta';
$image_where = "post_type = 'attachment' AND post_mime_type LIKE 'image/%'";
$ai_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ai_table)) === $ai_table;

$before = [
	'image_attachments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$posts_table} WHERE {$image_where}"),
	'alt_rows' => (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$postmeta_table} pm
		INNER JOIN {$posts_table} p ON p.ID = pm.post_id
		WHERE pm.meta_key = '_wp_attachment_image_alt' AND p.{$image_where}"
	),
	'ai_rows' => $ai_exists ? (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$ai_table} ai
		INNER JOIN {$posts_table} p ON p.ID = ai.attach_id
		WHERE p.{$image_where}"
	) : 0,
];

$wpdb->query('START TRANSACTION');

$posts_cleared = $wpdb->query(
	"UPDATE {$posts_table}
	SET post_title = '', post_excerpt = '', post_content = ''
	WHERE {$image_where}"
);

$alt_deleted = $wpdb->query(
	"DELETE pm FROM {$postmeta_table} pm
	INNER JOIN {$posts_table} p ON p.ID = pm.post_id
	WHERE pm.meta_key = '_wp_attachment_image_alt' AND p.{$image_where}"
);

$ai_deleted = 0;
if ($ai_exists) {
	$ai_deleted = $wpdb->query(
		"DELETE ai FROM {$ai_table} ai
		INNER JOIN {$posts_table} p ON p.ID = ai.attach_id
		WHERE p.{$image_where}"
	);
}

$errors = [];
if ($posts_cleared === false) {
	$errors['posts'] = $wpdb->last_error;
}
if ($alt_deleted === false) {
	$errors['alt'] = $wpdb->last_error;
}
if ($ai_exists && $ai_deleted === false) {
	$errors['ai'] = $wpdb->last_error;
}

if ($errors !== []) {
	$wpdb->query('ROLLBACK');
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');
	echo wp_json_encode(
		[
			'ok' => false,
			'errors' => $errors,
		],
		JSON_PRETTY_PRINT
	);
	exit;
}

$wpdb->query('COMMIT');

$after = [
	'image_attachments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$posts_table} WHERE {$image_where}"),
	'alt_rows' => (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$postmeta_table} pm
		INNER JOIN {$posts_table} p ON p.ID = pm.post_id
		WHERE pm.meta_key = '_wp_attachment_image_alt' AND p.{$image_where}"
	),
	'ai_rows' => $ai_exists ? (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$ai_table} ai
		INNER JOIN {$posts_table} p ON p.ID = ai.attach_id
		WHERE p.{$image_where}"
	) : 0,
];

header('Content-Type: application/json; charset=utf-8');
echo wp_json_encode(
	[
		'ok' => true,
		'before' => $before,
		'changes' => [
			'posts_cleared' => (int) $posts_cleared,
			'alt_deleted' => (int) $alt_deleted,
			'ai_deleted' => (int) $ai_deleted,
		],
		'after' => $after,
	],
	JSON_PRETTY_PRINT
);
