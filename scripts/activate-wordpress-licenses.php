<?php

use DynamicContentForElementor\Plugin;
use ElementorPro\License\Admin;
use ElementorPro\License\API;
use Jet_Dashboard\Dashboard;
use Jet_Dashboard\License_Manager;

$configPath = getenv('CENTRO_PROVISION_CONFIG') ?: '/etc/centro-wordpress-provision.env';
$config = parse_ini_file($configPath);
if (! is_array($config)) {
    throw new RuntimeException('Configurazione licenze non leggibile.');
}

$elementorKey = $config['ELEMENTOR_PRO_LICENSE'] ?? '';
$dynamicKey = $config['DYNAMIC_CONTENT_LICENSE'] ?? '';
$jetKey = $config['JET_PLUGINS_LICENSE'] ?? '';

if (! $elementorKey || ! $dynamicKey || ! $jetKey) {
    throw new RuntimeException('Una o più licenze non sono configurate.');
}

$elementorData = API::activate_license($elementorKey);
if (is_wp_error($elementorData)) {
    throw new RuntimeException('Attivazione Elementor Pro non riuscita: '.$elementorData->get_error_message());
}
if (! is_array($elementorData) || empty($elementorData['success'])) {
    throw new RuntimeException('Attivazione Elementor Pro non riuscita.');
}
Admin::set_license_key($elementorKey);
API::set_license_data($elementorData);

$dynamicLicense = Plugin::instance()->license_system;
[$dynamicSuccess, $dynamicMessage] = $dynamicLicense->activate_new_license_key($dynamicKey);
if (! $dynamicSuccess) {
    throw new RuntimeException('Attivazione Dynamic Content non riuscita: '.$dynamicMessage);
}

$jetManager = Dashboard::get_instance()->license_manager;
if (! $jetManager && class_exists(License_Manager::class)) {
    $jetManager = (new ReflectionClass(License_Manager::class))->newInstanceWithoutConstructor();
}
if (! $jetManager) {
    throw new RuntimeException('Il gestore licenze Jet Plugins non è disponibile.');
}
$jetResponse = $jetManager->license_action_query('activate_license', $jetKey);
if (! is_array($jetResponse) || ($jetResponse['status'] ?? 'error') === 'error' || empty($jetResponse['data'])) {
    throw new RuntimeException('Attivazione licenza Jet Plugins non riuscita.');
}
$jetData = $jetManager->maybe_modify_responce_data($jetResponse['data']);
$jetData = $jetManager->maybe_modify_tm_responce_data($jetData);
$jetManager->update_license_list($jetKey, $jetData);

WP_CLI::success('Licenze premium attivate.');
