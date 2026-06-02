<?php

if (!defined('GLPI_ROOT')) {
    include '../../../inc/includes.php';
}

$from_tab = $GLOBALS['PLUGIN_CREDITALERT_FROM_TAB'] ?? false;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);

$configItem = PluginCreditalertConfig::getInstance();
$config = PluginCreditalertConfig::getConfig();
$csrf_token = Session::getNewCSRFToken(true);

$form_action = $configItem->getFormURL();
if ($from_tab) {
    $form_action .= '?forcetab=' . urlencode('PluginCreditalertConfig$1');
}
$redirect = $form_action;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (
        empty($_POST['_glpi_csrf_token'])
        || !defined('GLPI_VERSION')
        || version_compare((string) GLPI_VERSION, '11.0.0', '<')
    ) {
        Session::checkCSRF($_POST, true);
    }
}

if (isset($_POST['update'])) {
    PluginCreditalertConfig::updateConfig($_POST);
    Html::back();
}
// Note: per-entity thresholds are managed via AJAX (front/ajax/entityconfig.php).

if (!$from_tab) {
    Html::header(
        PluginCreditalertConfig::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'plugins',
        'creditalert',
        'config'
    );
}

// --- Onglets internes (style GLPI natif) ---
echo "<ul class='nav nav-tabs mb-3' id='creditalert_config_tabs' role='tablist'>";
echo "<li class='nav-item' role='presentation'>";
echo "<a class='nav-link active' id='creditalert-tab-general-btn' href='#creditalert-tab-general' role='tab'"
    . " onclick='return creditalertShowTab(event, \"creditalert-tab-general\")'>"
    . "<i class='fa-solid fa-sliders me-1'></i>" . __('Configuration generale', 'creditalert') . "</a>";
echo "</li>";
echo "<li class='nav-item' role='presentation'>";
echo "<a class='nav-link' id='creditalert-tab-entities-btn' href='#creditalert-tab-entities' role='tab'"
    . " onclick='return creditalertShowTab(event, \"creditalert-tab-entities\")'>"
    . "<i class='fa-solid fa-building me-1'></i>" . __('Seuils par entite', 'creditalert') . "</a>";
echo "</li>";
echo "</ul>";

echo "<div class='tab-content' id='creditalert_config_tabs_content'>";

// === Onglet 1 : configuration generale ===
echo "<div class='tab-pane active' id='creditalert-tab-general' role='tabpanel' style='display:block;'>";

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
echo "<label class='fw-semibold mb-1 d-block'>" . __('Seuil global (%)', 'creditalert') . "</label>";
echo Html::input('alert_threshold', [
    'type'  => 'number',
    'min'   => 0,
    'max'   => 100,
    'value' => $config['alert_threshold'],
    'class' => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Couleur avertissement', 'creditalert') . "</label>";
echo Html::input('color_warning', ['value' => $config['color_warning'], 'type' => 'color', 'class' => 'form-control form-control-color']);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Couleur depassement', 'creditalert') . "</label>";
echo Html::input('color_over', ['value' => $config['color_over'], 'type' => 'color', 'class' => 'form-control form-control-color']);
echo "</div>";

echo "<div class='col-md-3'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Destinataires (emails separes par des virgules)', 'creditalert') . "</label>";
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
echo "<label class='fw-semibold mb-1 d-block'>" . __('Nom de la table credit', 'creditalert') . "</label>";
echo Html::input('credit_table', ['value' => $config['credit_table'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Nom de la table de consommation', 'creditalert') . "</label>";
echo Html::input('consumption_table', ['value' => $config['consumption_table'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ quantite vendue', 'creditalert') . "</label>";
echo Html::input('field_sold', ['value' => $config['field_sold'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ quantite consommee', 'creditalert') . "</label>";
echo Html::input('field_used', ['value' => $config['field_used'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ entite', 'creditalert') . "</label>";
echo Html::input('field_entity', ['value' => $config['field_entity'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ client/libelle', 'creditalert') . "</label>";
echo Html::input('field_client', ['value' => $config['field_client'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ FK consommation', 'creditalert') . "</label>";
echo Html::input('field_fk_usage', ['value' => $config['field_fk_usage'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ date de fin', 'creditalert') . "</label>";
echo Html::input('field_end_date', ['value' => $config['field_end_date'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ actif (0/1)', 'creditalert') . "</label>";
echo Html::input('field_is_active', ['value' => $config['field_is_active'], 'class' => 'form-control']);
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Champ ticket (table consommation)', 'creditalert') . "</label>";
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
echo "<label class='fw-semibold mb-1 d-block'>" . __('Nom du fichier', 'creditalert') . "</label>";
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

echo "</div>"; // fin onglet general

// === Onglet 2 : seuils par entite ===
echo "<div class='tab-pane' id='creditalert-tab-entities' role='tabpanel' style='display:none;'>";

// --- Seuils par entite (gestion AJAX, sans rechargement) ---
$entityAjaxUrl = dirname((string) $configItem->getFormURL()) . '/ajax/entityconfig.php';

echo "<div class='card mb-3'>";
echo "<div class='card-header'><h3 class='card-title mb-0'>" . __('Seuils par entite', 'creditalert') . "</h3></div>";
echo "<div class='card-body'>";

echo "<div id='creditalert_entity_msg'></div>";

echo "<div class='row g-3 align-items-end mb-3'>";
echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . Entity::getTypeName(1) . "</label>";
Dropdown::show('Entity', [
    'name'        => 'entities_id',
    'entity'      => $_SESSION['glpiactive_entity'] ?? 0,
    'entity_sons' => true,
    'width'       => '100%',
]);
echo "</div>";

echo "<div class='col-md-2'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Seuil (%)', 'creditalert') . "</label>";
echo "<input type='number' min='0' max='100' id='creditalert_threshold_entity' class='form-control' placeholder='" . Html::cleanInputText(__('Herite si vide', 'creditalert')) . "'>";
echo "</div>";

echo "<div class='col-md-4'>";
echo "<label class='fw-semibold mb-1 d-block'>" . __('Emails (CSV)', 'creditalert') . "</label>";
echo "<input type='text' id='creditalert_emails_entity' class='form-control' placeholder='" . Html::cleanInputText(__('Emails (CSV)', 'creditalert')) . "'>";
echo "</div>";

echo "<div class='col-md-2 text-end'>";
echo "<button type='button' id='creditalert_entity_add_btn' class='btn btn-primary'><i class='ti ti-plus'></i> " . __('Ajouter', 'creditalert') . "</button>";
echo "</div>";
echo "</div>"; // row

echo "<div id='creditalert_entity_table'>" . PluginCreditalertConfig::renderEntityThresholdsTable() . "</div>";

echo "</div>"; // card-body
echo "</div>"; // card

// Modal d'edition d'un seuil par entite
echo "<div class='modal fade' id='creditalert_edit_modal' tabindex='-1' aria-hidden='true'>";
echo "<div class='modal-dialog'><div class='modal-content'>";
echo "<div class='modal-header'>";
echo "<h5 class='modal-title'>" . __('Modifier le seuil', 'creditalert') . "</h5>";
echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
echo "</div>";
echo "<div class='modal-body'>";
echo "<input type='hidden' id='creditalert_edit_eid'>";
echo "<div class='mb-3'><span class='text-muted'>" . Entity::getTypeName(1) . " :</span> <strong id='creditalert_edit_name'></strong></div>";
echo "<div class='mb-3'>";
echo "<label class='fw-semibold mb-1 d-block' for='creditalert_edit_threshold'>" . __('Seuil (%)', 'creditalert') . "</label>";
echo "<input type='number' min='0' max='100' id='creditalert_edit_threshold' class='form-control' placeholder='" . Html::cleanInputText(__('Herite si vide', 'creditalert')) . "'>";
echo "</div>";
echo "<div class='mb-3'>";
echo "<label class='fw-semibold mb-1 d-block' for='creditalert_edit_emails'>" . __('Emails (CSV)', 'creditalert') . "</label>";
echo "<input type='text' id='creditalert_edit_emails' class='form-control'>";
echo "</div>";
echo "</div>";
echo "<div class='modal-footer'>";
echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>" . _sx('button', 'Cancel') . "</button>";
echo "<button type='button' class='btn btn-primary' id='creditalert_edit_save'>" . _sx('button', 'Save') . "</button>";
echo "</div>";
echo "</div></div></div>";

echo "</div>"; // fin onglet entites

echo "</div>"; // fin tab-content

// Sous-onglets internes : visibilite geree directement en CSS inline (general
// visible, entites cache) pour ne JAMAIS dependre du timing d'execution du JS.
// GLPI met en cache le contenu de l'onglet (cf. Ajax::createTabs) : ce script ne
// se relance pas quand on revient sur l'onglet. On definit donc une fonction
// globale pour le clic + un ecouteur qui remet "Configuration generale" a chaque
// retour sur l'onglet GLPI.
$tabJs = <<<JS
(function() {
    var PANES = ['creditalert-tab-general', 'creditalert-tab-entities'];

    window.creditalertShowTab = function(e, target) {
        if (e && e.preventDefault) { e.preventDefault(); }
        PANES.forEach(function(id) {
            var pane = document.getElementById(id);
            if (pane) { pane.style.display = (id === target) ? 'block' : 'none'; }
            var btn = document.getElementById(id + '-btn');
            if (btn) { btn.classList.toggle('active', id === target); }
        });
        return false;
    };

    // GLPI ne recharge pas l'onglet (contenu mis en cache) : a chaque fois qu'un
    // onglet GLPI est re-affiche, on remet l'onglet "Configuration generale".
    if (!window.creditalertTabResetBound) {
        window.creditalertTabResetBound = true;
        document.addEventListener('shown.bs.tab', function() {
            if (document.getElementById('creditalert-tab-general')) {
                window.creditalertShowTab(null, 'creditalert-tab-general');
            }
        });
    }
})();
JS;
echo Html::scriptBlock($tabJs);

// Gestion AJAX des seuils par entité (ajout rapide / suppression / édition).
$entityAjaxUrlJs = json_encode($entityAjaxUrl);
$csrfJs = json_encode($csrf_token);
$msgConfirmDelete = json_encode(__('Supprimer ce seuil par entite ?', 'creditalert'));
$entityJs = <<<JS
$(function() {
    var url = {$entityAjaxUrlJs};
    var token = {$csrfJs};
    var \$table = $('#creditalert_entity_table');
    var \$msg = $('#creditalert_entity_msg');

    function flash(resp) {
        if (!resp || !resp.message) { return; }
        var cls = resp.success ? 'alert-success' : 'alert-danger';
        \$msg.html("<div class='alert " + cls + " py-2'>" + $('<div>').text(resp.message).html() + "</div>");
        window.setTimeout(function() { \$msg.find('.alert').fadeOut(300, function() { $(this).remove(); }); }, 2500);
    }

    function send(data, cb) {
        data._glpi_csrf_token = token;
        $.post(url, data, function(resp) {
            if (resp && typeof resp.html !== 'undefined') { \$table.html(resp.html); }
            flash(resp);
            if (cb) { cb(resp); }
        }, 'json');
    }

    // Ajout rapide (l'utilisateur peut enchaîner les ajouts).
    $('#creditalert_entity_add_btn').on('click', function() {
        send({
            action: 'add',
            entities_id: $('select[name="entities_id"]').val(),
            alert_threshold_entity: $('#creditalert_threshold_entity').val(),
            notification_emails_entity: $('#creditalert_emails_entity').val()
        }, function(resp) {
            if (resp && resp.success) {
                $('#creditalert_threshold_entity').val('');
                $('#creditalert_emails_entity').val('');
                $('select[name="entities_id"]').val(0).trigger('change');
            }
        });
    });

    // Suppression (déléguée car la table est régénérée).
    \$table.on('click', '.creditalert-del-entity', function() {
        if (!window.confirm({$msgConfirmDelete})) { return; }
        send({ action: 'delete', entities_id: $(this).data('eid') });
    });

    // Édition -> ouverture du modal pré-rempli.
    \$table.on('click', '.creditalert-edit-entity', function() {
        var b = $(this);
        $('#creditalert_edit_eid').val(b.data('eid'));
        $('#creditalert_edit_name').text(b.data('name'));
        $('#creditalert_edit_threshold').val(b.attr('data-threshold'));
        $('#creditalert_edit_emails').val(b.attr('data-emails'));
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('creditalert_edit_modal')).show();
        }
    });

    // Enregistrement de l'édition.
    $('#creditalert_edit_save').on('click', function() {
        send({
            action: 'update',
            entities_id: $('#creditalert_edit_eid').val(),
            alert_threshold_entity: $('#creditalert_edit_threshold').val(),
            notification_emails_entity: $('#creditalert_edit_emails').val()
        }, function(resp) {
            if (resp && resp.success && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('creditalert_edit_modal')).hide();
            }
        });
    });
});
JS;
echo Html::scriptBlock($entityJs);

if (!$from_tab) {
    Html::footer();
}
