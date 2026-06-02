<?php

include('../../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);

// PluginCreditalertEntityConfig is defined inside config.class.php (secondary class),
// so it cannot be autoloaded on its own: load the config class first.
class_exists('PluginCreditalertConfig');

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

// Preserve the CSRF token so repeated AJAX calls (mass add/delete) keep working
// without a full page reload.
Session::checkCSRF($_POST, true);

$action  = (string) ($_POST['action'] ?? '');
$success = true;
$message = '';

switch ($action) {
    case 'add':
    case 'update':
        $eid = (int) ($_POST['entities_id'] ?? 0);
        if ($eid <= 0) {
            $success = false;
            $message = __('Veuillez selectionner une entite.', 'creditalert');
            break;
        }
        $thrInput = $_POST['alert_threshold_entity'] ?? '';
        $threshold = ($thrInput === '' ? null : (int) $thrInput);
        PluginCreditalertEntityConfig::upsert([
            'entities_id'         => $eid,
            'alert_threshold'     => $threshold,
            'notification_emails' => $_POST['notification_emails_entity'] ?? '',
        ]);
        $message = $action === 'add'
            ? __('Seuil par entite enregistre.', 'creditalert')
            : __('Seuil par entite mis a jour.', 'creditalert');
        break;

    case 'delete':
        $eid = (int) ($_POST['entities_id'] ?? 0);
        if ($eid > 0) {
            PluginCreditalertEntityConfig::deleteForEntity($eid);
        }
        $message = __('Seuil par entite supprime.', 'creditalert');
        break;

    default:
        $success = false;
        $message = __('Action inconnue.', 'creditalert');
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'html'    => PluginCreditalertConfig::renderEntityThresholdsTable(),
]);
