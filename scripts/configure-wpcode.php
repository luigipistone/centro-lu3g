<?php

if (! post_type_exists('wpcode')) {
    throw new RuntimeException('WPCode non è attivo.');
}

$snippets = [
    [
        'title' => 'Disable Gutenberg Editor',
        'code' => "add_filter( 'use_block_editor_for_post', '__return_false', 10 );\nadd_filter( 'use_block_editor_for_post_type', '__return_false', 10 );",
        'type' => 'php',
        'location' => 'everywhere',
    ],
    [
        'title' => 'Disable Widget Blocks',
        'code' => "add_filter( 'use_widgets_block_editor', '__return_false' );",
        'type' => 'php',
        'location' => 'everywhere',
    ],
    [
        'title' => 'Snippet CSS generale',
        'code' => '/* CSS generale */',
        'type' => 'css',
        'location' => 'site_wide_header',
    ],
];

foreach ($snippets as $definition) {
    global $wpdb;

    $existing = get_posts([
        'post_type' => 'wpcode',
        'post_status' => ['publish', 'draft'],
        'title' => $definition['title'],
        'posts_per_page' => 1,
    ]);

    $now = current_time('mysql');
    $post = [
        'post_status' => 'publish',
        'post_title' => $definition['title'],
        'post_content' => $definition['code'],
        'post_name' => sanitize_title($definition['title']),
        'post_modified' => $now,
        'post_modified_gmt' => get_gmt_from_date($now),
    ];

    if ($existing) {
        $snippetId = $existing[0]->ID;
        $saved = $wpdb->update($wpdb->posts, $post, ['ID' => $snippetId]);
    } else {
        $saved = $wpdb->insert($wpdb->posts, [
            ...$post,
            'post_author' => 0,
            'post_date' => $now,
            'post_date_gmt' => get_gmt_from_date($now),
            'post_excerpt' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_parent' => 0,
            'menu_order' => 0,
            'post_type' => 'wpcode',
            'comment_count' => 0,
        ]);
        $snippetId = (int) $wpdb->insert_id;
    }

    if ($saved === false || ! $snippetId) {
        throw new RuntimeException('Impossibile salvare lo snippet '.$definition['title'].': '.$wpdb->last_error);
    }

    clean_post_cache($snippetId);
    wp_set_object_terms($snippetId, $definition['type'], 'wpcode_type', false);
    wp_set_object_terms($snippetId, $definition['location'], 'wpcode_location', false);
    update_post_meta($snippetId, '_wpcode_auto_insert', 1);
}

if (function_exists('wpcode')) {
    wpcode()->cache->cache_all_loaded_snippets();
}

WP_CLI::success('Snippet WPCode configurati.');
