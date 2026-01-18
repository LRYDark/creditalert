<?php

if (!defined('GLPI_ROOT')) {
    include '../../../inc/includes.php';
}

$from_tab = $GLOBALS['PLUGIN_CREDITALERT_FROM_TAB'] ?? false;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);

$configItem = PluginCreditalertConfig::getInstance();
$config = PluginCreditalertConfig::getConfig();
$csrf_token = Session::getNewCSRFToken();

$form_action = $configItem->getFormURL();
if ($from_tab) {
    $form_action .= '?forcetab=' . urlencode('PluginCreditalertConfig$1');
}
$redirect = $form_action;

if (isset($_POST['update'])) {
    PluginCreditalertConfig::updateConfig($_POST);
    Html::back();
}

if (isset($_POST['add_entity_config'])) {
    $thresholdInput = $_POST['alert_threshold_entity'] ?? '';
    $thresholdValue = $thresholdInput === '' ? null : (int) $thresholdInput;
    PluginCreditalertEntityConfig::upsert([
        'entities_id'         => (int) $_POST['entities_id'],
        'alert_threshold'     => $thresholdValue,
        'notification_emails' => $_POST['notification_emails_entity'] ?? '',
    ]);
    Html::back();
}

if (isset($_POST['delete_entity_config'])) {
    PluginCreditalertEntityConfig::deleteForEntity((int) $_POST['delete_entity_config']);
    Html::back();
}

if (!$from_tab) {
    Html::header(
        PluginCreditalertConfig::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'plugins',
        'creditalert',
        'config'
    );
}

// --- Form global ---
echo "<form name='creditalert_config' method='post' action='" . $form_action . "' class='mb-3'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $csrf_token]);
echo Html::hidden('id', ['value' => $configItem->fields['id'] ?? 1]);

// Card General
echo "<div class='card mb-3'>";
echo "<div class='card-header'><h3 class='card-title mb-0'>" . __('Alertes generales', 'creditalert') . "</h3></div>";
echo "<div class='card-body'>";
echo "<div class='row g-3'>";

echo "<div class='col-md-3'>";
echo "<label class='form-label mb-1'>" . __('Seuil global (%)', 'creditalert') . "</label>";
echo Html::input('alert_threshold', [
    'type'  => 'number',
    'min'   => 0,
    'max'   => 100,
    'value' => $config['alert_threshold'],
    'class' => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='form-label mb-1'>" . __('Couleur avertissement', 'creditalert') . "</label>";
echo Html::input('color_warning', ['value' => $config['color_warning'], 'type' => 'color', 'class' => 'form-control form-control-color']);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='form-label mb-1'>" . __('Couleur depassement', 'creditalert') . "</label>";
echo Html::input('color_over', ['value' => $config['color_over'], 'type' => 'color', 'class' => 'form-control form-control-color']);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='form-label mb-1'>" . __('Destinataires (emails separes par des virgules)', 'creditalert') . "</label>";
echo "<textarea name='notification_emails' rows='2' class='form-control'>" . htmlspecialchars($config['notification_emails']) . "</textarea>";
echo "</div>";

echo "</div>"; // row
echo "</div>"; // card-body
echo "</div>"; // card

// Card Mapping
echo "<div class='card mb-3'>";
echo "<div class='card-header'><h3 class='card-title mb-0'>" . __('Mapping plugin Credit', 'creditalert') . "</h3></div>";
echo "<div class='card-body'>";
echo "<div class='row g-3'>";

echo "<div class='col-md-6'>";
echo "<label class='form-label mb-1'>" . __('Nom de la table credit', 'creditalert') . "</label>";
echo Html::input('credit_table', ['value' => $config['credit_table'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label mb-1'>" . __('Nom de la table de consommation', 'creditalert') . "</label>";
echo Html::input('consumption_table', ['value' => $config['consumption_table'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ quantite vendue', 'creditalert') . "</label>";
echo Html::input('field_sold', ['value' => $config['field_sold'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ quantite consommee', 'creditalert') . "</label>";
echo Html::input('field_used', ['value' => $config['field_used'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ entite', 'creditalert') . "</label>";
echo Html::input('field_entity', ['value' => $config['field_entity'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ client/libelle', 'creditalert') . "</label>";
echo Html::input('field_client', ['value' => $config['field_client'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ FK consommation', 'creditalert') . "</label>";
echo Html::input('field_fk_usage', ['value' => $config['field_fk_usage'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ date de fin', 'creditalert') . "</label>";
echo Html::input('field_end_date', ['value' => $config['field_end_date'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ actif (0/1)', 'creditalert') . "</label>";
echo Html::input('field_is_active', ['value' => $config['field_is_active'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Champ ticket (table consommation)', 'creditalert') . "</label>";
echo Html::input('field_ticket', ['value' => $config['field_ticket'], 'class' => 'form-control']);
echo "</div>";

echo "</div>"; // row
echo "</div>"; // card-body
echo "</div>"; // card

// Card CSV exports
echo "<div class='card mb-3'>";
echo "<div class='card-header'><h3 class='card-title mb-0'>" . __('Exports CSV', 'creditalert') . "</h3></div>";
echo "<div class='card-body'>";
echo "<div class='row g-3 align-items-end'>";

echo "<div class='col-md-6'>";
echo "<label class='form-label mb-1'>" . __('Nom du fichier', 'creditalert') . "</label>";
echo Html::input('export_filename_base', [
    'value' => $config['export_filename_base'] ?? 'Export_Client_Glpi',
    'class' => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<div class='form-check mt-4'>";
echo "<input class='form-check-input' type='checkbox' name='export_filename_include_date' id='creditalert_export_filename_date' value='1'"
    . (!empty($config['export_filename_include_date']) ? ' checked' : '') . ">";
echo "<label class='form-check-label' for='creditalert_export_filename_date'>"
    . __('Ajouter la date', 'creditalert') . "</label>";
echo "</div>";
echo "</div>";

echo "<div class='col-md-3'>";
echo "<div class='form-check mt-4'>";
echo "<input class='form-check-input' type='checkbox' name='export_filename_include_entity' id='creditalert_export_filename_entity' value='1'"
    . (!empty($config['export_filename_include_entity']) ? ' checked' : '') . ">";
echo "<label class='form-check-label' for='creditalert_export_filename_entity'>"
    . __('Ajouter l\'entite', 'creditalert') . "</label>";
echo "</div>";
echo "</div>";

echo "</div>"; // row
echo "</div>"; // card-body
echo "</div>"; // card

// Save button
echo "<div class='card mb-3'>";
echo "<div class='card-body text-center'>";
echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
echo "</div>";
echo "</div>";

Html::closeForm();

// --- Form entity overrides ---
echo "<form name='creditalert_entities' method='post' action='" . $form_action . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $csrf_token]);

echo "<div class='card mb-3'>";
echo "<div class='card-header'><h3 class='card-title mb-0'>" . __('Seuils par entite', 'creditalert') . "</h3></div>";
echo "<div class='card-body'>";

echo "<div class='row g-3 align-items-end'>";
echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . Entity::getTypeName(1) . "</label>";
Dropdown::show('Entity', [
    'name'   => 'entities_id',
    'entity' => $_SESSION['glpiactive_entity'] ?? 0,
    'entity_sons' => true,
    'class'  => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-2'>";
echo "<label class='form-label mb-1'>" . __('Seuil (%)', 'creditalert') . "</label>";
echo Html::input('alert_threshold_entity', [
    'type'        => 'number',
    'min'         => 0,
    'max'         => 100,
    'value'       => '',
    'placeholder' => __('Seuil (%)', 'creditalert'),
    'class'       => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='form-label mb-1'>" . __('Emails (CSV)', 'creditalert') . "</label>";
echo Html::input('notification_emails_entity', [
    'value'       => '',
    'placeholder' => __('Emails (CSV)', 'creditalert'),
    'class'       => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-2 text-end'>";
echo Html::submit(__('Enregistrer', 'creditalert'), ['name' => 'add_entity_config', 'class' => 'btn btn-primary']);
echo "</div>";
echo "</div>"; // row

$entityConfigs = PluginCreditalertEntityConfig::getAllConfigs();
if (!empty($entityConfigs)) {
    echo "<div class='table-responsive mt-4'>";
    echo "<table class='table table-hover'>";
    echo "<thead><tr><th>" . Entity::getTypeName(1) . "</th><th>" . __('Seuil', 'creditalert') . "</th><th>" . __('Emails', 'creditalert') . "</th><th class='text-end'>" . __('Actions') . "</th></tr></thead>";
    echo "<tbody>";
    foreach ($entityConfigs as $entityConfig) {
        $thresholdLabel = $entityConfig['alert_threshold'] === null || $entityConfig['alert_threshold'] === ''
            ? __('Herite', 'creditalert')
            : intval($entityConfig['alert_threshold']) . '%';
        echo "<tr>";
        echo "<td>" . Dropdown::getDropdownName('glpi_entities', $entityConfig['entities_id']) . "</td>";
        echo "<td>" . $thresholdLabel . "</td>";
        echo "<td>" . htmlspecialchars($entityConfig['notification_emails'] ?? '') . "</td>";
        echo "<td class='text-end'>";
        echo Html::submit(__('Supprimer'), ['name' => 'delete_entity_config', 'value' => (int) $entityConfig['entities_id'], 'class' => 'btn btn-danger btn-sm']);
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
}

echo "</div>"; // card-body
echo "</div>"; // card

Html::closeForm();

if (!$from_tab) {
    Html::footer();
}
