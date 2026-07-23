<?php

if (! class_exists('WPCode_Snippet')) {
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

    $snippet = new WPCode_Snippet($existing ? $existing[0]->ID : []);
    $snippet->title = $definition['title'];
    $snippet->code = $definition['code'];
    $snippet->code_type = $definition['type'];
    $snippet->location = $definition['location'];
    $snippet->auto_insert = 1;
    $snippet->active = true;

    if (! $snippet->save()) {
        throw new RuntimeException('Impossibile salvare lo snippet '.$definition['title'].'.');
    }
}

WP_CLI::success('Snippet WPCode configurati.');
