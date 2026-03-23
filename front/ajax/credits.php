<?php

include('../../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

header('Content-Type: application/json; charset=utf-8');

$entityId = (int) ($_GET['entities_id'] ?? ($_POST['entities_id'] ?? 0));
if ($entityId <= 0) {
    echo json_encode([]);
    exit;
}

$credits = PluginCreditalertConsumption::getCreditsForEntity($entityId);

$result = [];
foreach ($credits as $creditId => $credit) {
    $result[] = [
        'id'           => (int) $creditId,
        'label'        => $credit['label'] ?? '',
        'entity_label' => $credit['entity_label'] ?? '',
        'expired'      => $credit['expired'] ?? false,
        'expire_date'  => $credit['expire_date'] ?? '',
        'active'       => $credit['active'] ?? true,
        'begin_year'   => $credit['begin_year'] ?? '',
        'percent'      => $credit['percent'] ?? '',
    ];
}

echo json_encode($result);
