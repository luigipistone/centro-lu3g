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
    $existing = get_posts([
        'post_type' => 'wpcode',
        'post_status' => ['publish', 'draft'],
        'title' => $definition['title'],
        'posts_per_page' => 1,
    ]);

    $post = [
        'post_type' => 'wpcode',
        'post_status' => 'publish',
        'post_title' => $definition['title'],
        'post_content' => $definition['code'],
    ];

    if ($existing) {
        $post['ID'] = $existing[0]->ID;
        $snippetId = wp_update_post($post, true);
    } else {
        $snippetId = wp_insert_post($post, true);
    }

    if (is_wp_error($snippetId)) {
        throw new RuntimeException('Impossibile salvare lo snippet '.$definition['title'].': '.$snippetId->get_error_message());
    }

    wp_set_object_terms($snippetId, $definition['type'], 'wpcode_type', false);
    wp_set_object_terms($snippetId, $definition['location'], 'wpcode_location', false);
    update_post_meta($snippetId, '_wpcode_auto_insert', 1);
}

if (function_exists('wpcode')) {
    wpcode()->cache->cache_all_loaded_snippets();
}

WP_CLI::success('Snippet WPCode configurati.');
