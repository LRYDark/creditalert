<?php

include('../../../inc/includes.php');

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

$credit_id = (int) ($_GET['credit_id'] ?? 0);
if ($credit_id <= 0) {
    Html::displayErrorAndDie(__('Credit manquant', 'creditalert'));
}

$config = PluginCreditalertConfig::getConfig();

$creditTable = $config['credit_table'];
$consumptionTable = $config['consumption_table'];
$fieldTicket = $config['field_ticket'];
$fieldFk = $config['field_fk_usage'];
$fieldUsed = $config['field_used'];

/** @var DBmysql $DB */
global $DB, $CFG_GLPI;

$tickets = [];
foreach ($DB->request([
    'SELECT' => [
        'glpi_tickets.id AS id',
        'glpi_tickets.name AS name',
        'glpi_tickets.status AS status',
        'glpi_tickets.date AS date',
        "$consumptionTable.$fieldUsed AS consumed",
        "$consumptionTable.date_creation AS consume_date",
    ],
    'FROM'   => $consumptionTable,
    'LEFT JOIN' => [
        'glpi_tickets' => [
            'ON' => [
                'glpi_tickets' => 'id',
                $consumptionTable => $fieldTicket,
            ],
        ],
    ],
    'WHERE'  => [
        $consumptionTable . '.' . $fieldFk => $credit_id,
    ],
]) as $row) {
    $tickets[] = $row;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "credit_{$credit_id}_tickets.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID ticket', 'Titre', 'Statut', 'Date du ticket', 'Date de consommation', 'Quantite consommee']);
    foreach ($tickets as $t) {
        fputcsv($out, [
            $t['id'],
            $t['name'],
            Ticket::getStatus($t['status']),
            $t['date'],
            $t['consume_date'],
            $t['consumed'],
        ]);
    }
    fclose($out);
    exit;
}

Html::header(
    __('Tickets consommant ce credit', 'creditalert'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'creditlist'
);

echo "<div class='card'><div class='card-body'>";
echo "<div class='d-flex justify-content-end mb-2'>";
$exportUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/credittickets.php?credit_id=' . $credit_id . '&export=csv';
echo "<a class='btn btn-secondary' href='$exportUrl'>" . __('Export CSV') . "</a>";
echo "</div>";

echo "<div class='table-responsive'>";
echo "<table class='table table-hover'>";
echo "<thead><tr>";
echo "<th>" . __('Ticket') . "</th>";
echo "<th>" . __('Statut') . "</th>";
echo "<th>" . __('Date du ticket') . "</th>";
echo "<th>" . __('Date de consommation', 'creditalert') . "</th>";
echo "<th class='text-end'>" . __('Quantite consommee', 'creditalert') . "</th>";
echo "</tr></thead><tbody>";

foreach ($tickets as $t) {
    $url = Ticket::getFormURLWithID((int) $t['id']);
    echo "<tr>";
    echo "<td><a href='{$url}'>" . Html::entities_deep($t['name']) . "</a></td>";
    echo "<td>" . Ticket::getStatus($t['status']) . "</td>";
    echo "<td>" . Html::convDateTime($t['date']) . "</td>";
    echo "<td>" . Html::convDateTime($t['consume_date']) . "</td>";
    echo "<td class='text-end'>" . (float) $t['consumed'] . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "</div></div></div>";

Html::footer();
