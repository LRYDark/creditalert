<?php

/**
 * Champ « Crédits » du filtre « Consommations par client » : crédits des entités choisies (sous-entités
 * comprises), réduits aux types de crédit saisis. Appelé à chaque changement d'entité ou de type, sans recharger
 * la page.
 */
Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

header('Content-Type: application/json; charset=utf-8');

$entityIds = PluginCreditalertConsumption::normalizeEntityIds($_GET['entities_id'] ?? []);
$types = PluginCreditalertConsumption::parseTypes($_GET['types'] ?? []);

$result = [];
foreach (PluginCreditalertConsumption::getFilterCredits($entityIds, $types) as $creditId => $credit) {
    $result[] = [
        'id'           => (int) $creditId,
        'text'         => (string) $credit['label'],
        'entity_label' => (string) $credit['entity_label'],
        'status'       => (string) $credit['status'],
        'begin_label'  => (string) $credit['begin_label'],
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
