<?php

if (! defined('ABSPATH')) {
    fwrite(STDERR, "WordPress non inizializzato.\n");
    exit(1);
}

$payloadPath = getenv('CENTRO_DESIGN_PAYLOAD');
$payload = $payloadPath && is_file($payloadPath)
    ? json_decode((string) file_get_contents($payloadPath), true)
    : null;

if (! is_array($payload) || ! isset($payload['colors'], $payload['typography'])) {
    fwrite(STDERR, "Configurazione design system non valida.\n");
    exit(1);
}

if (! did_action('elementor/loaded') || ! class_exists('\Elementor\Plugin')) {
    fwrite(STDERR, "Elementor non è attivo.\n");
    exit(1);
}

$kitId = (int) get_option('elementor_active_kit');
if ($kitId <= 0 || get_post_type($kitId) !== 'elementor_library') {
    fwrite(STDERR, "Kit Elementor attivo non disponibile.\n");
    exit(1);
}

$roles = [
    'primary' => 'Primario',
    'secondary' => 'Secondario',
    'text' => 'Testo',
    'accent' => 'Accento',
];
$settings = get_post_meta($kitId, '_elementor_page_settings', true);
$settings = is_array($settings) ? $settings : [];
$systemColors = [];
$systemTypography = [];

foreach ($roles as $role => $title) {
    $color = strtoupper(trim((string) ($payload['colors'][$role] ?? '')));
    $font = $payload['typography'][$role] ?? [];
    $family = trim((string) ($font['family'] ?? ''));
    $weight = (string) ((int) ($font['weight'] ?? 400));

    if (! preg_match('/^#[0-9A-F]{6}$/', $color) || $family === '' || ! preg_match('/^[1-9]00$/', $weight)) {
        fwrite(STDERR, "Valore non valido per il ruolo {$role}.\n");
        exit(1);
    }

    $systemColors[] = [
        '_id' => $role,
        'title' => $title,
        'color' => $color,
    ];
    $systemTypography[] = [
        '_id' => $role,
        'title' => $title,
        'typography_typography' => 'custom',
        'typography_font_family' => $family,
        'typography_font_weight' => $weight,
    ];
}

$settings['system_colors'] = $systemColors;
$settings['system_typography'] = $systemTypography;
update_post_meta($kitId, '_elementor_page_settings', $settings);

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action('elementor/core/files/clear_cache');

fwrite(STDOUT, "Design system applicato al Kit Elementor {$kitId}.\n");
