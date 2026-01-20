<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_REASSIGN);

/** @var array $CFG_GLPI */
global $CFG_GLPI;

$ticketId = (int) ($_GET['tickets_id'] ?? ($_POST['tickets_id'] ?? 0));
if ($ticketId <= 0) {
    Html::displayErrorAndDie(__('Missing ticket.', 'creditalert'));
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    Html::displayErrorAndDie(__('Ticket not found.', 'creditalert'));
}

$canedit = false;
if (Session::haveRight(Entity::$rightname, UPDATE)) {
    $canedit = true;
} elseif (
    $ticket->canEdit($ticketId)
    && !in_array($ticket->fields['status'], array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray()))
) {
    $canedit = true;
}

if (!$canedit) {
    Html::displayRightError();
}

$plugin = new Plugin();
if (!$plugin->isInstalled('credit') || !$plugin->isActivated('credit')) {
    Html::displayErrorAndDie(__('Credit plugin is required.', 'creditalert'));
}

$config = PluginCreditalertConfig::getConfig();
$creditTable = $config['credit_table'];
$consumptionTable = $config['consumption_table'];
$fieldTicket = $config['field_ticket'];
$fieldFk = $config['field_fk_usage'];
$fieldUsed = $config['field_used'];
$fieldClient = $config['field_client'];
$fieldEntity = $config['field_entity'];
$fieldSold = $config['field_sold'];
$fieldEnd = $config['field_end_date'];
$fieldActive = $config['field_is_active'];

if ($fieldTicket === '') {
    $fieldTicket = 'tickets_id';
}
if ($fieldFk === '') {
    $fieldFk = 'plugin_credit_entities_id';
}
if ($fieldEntity === '') {
    $fieldEntity = 'entities_id';
}
if ($fieldSold === '') {
    $fieldSold = 'quantity';
}
if ($fieldUsed === '') {
    $fieldUsed = 'consumed';
}
if ($fieldClient === '') {
    $fieldClient = 'name';
}

$baseUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ticket.reassigncredit.php?tickets_id=' . $ticketId;
if (!empty($_REQUEST['_in_modal'])) {
    $baseUrl .= '&_in_modal=1';
}

/** @var DBmysql $DB */
global $DB;

$ticketEntityId = (int) $ticket->getEntityID();
$baseEntityId = $ticketEntityId;
$entityScope = getSonsOf('glpi_entities', $ticketEntityId);
if (!is_array($entityScope) || empty($entityScope)) {
    $entityScope = [$ticketEntityId];
}
$entityScope = Session::getMatchingActiveEntities($entityScope);
if (empty($entityScope)) {
    $entityScope = [$ticketEntityId];
}

$selectedEntityLabel = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
    Dropdown::getDropdownName('glpi_entities', $baseEntityId)
);
$selectedEntityParts = array_values(array_filter(
    array_map('trim', explode('>', $selectedEntityLabel)),
    static function ($part) {
        return $part !== '';
    }
));
$getRelativeEntityLabel = static function (string $fullLabel, array $selectedParts): string {
    $parts = array_values(array_filter(
        array_map('trim', explode('>', $fullLabel)),
        static function ($part) {
            return $part !== '';
        }
    ));
    if (empty($parts)) {
        return $fullLabel;
    }
    if (!empty($selectedParts) && count($parts) >= count($selectedParts)) {
        $match = true;
        foreach ($selectedParts as $index => $part) {
            if (!isset($parts[$index]) || $parts[$index] !== $part) {
                $match = false;
                break;
            }
        }
        if ($match) {
            if (count($parts) === count($selectedParts)) {
                return (string) end($parts);
            }
            $relative = array_slice($parts, count($selectedParts) - 1);
            return implode(' > ', $relative);
        }
    }
    return (string) end($parts);
};

$errors = [];

if (isset($_POST['reassign'])) {
    $newCreditId = (int) ($_POST['new_credit_id'] ?? 0);
    $selected = $_POST['consumption_ids'] ?? [];
    if (!is_array($selected)) {
        $selected = [$selected];
    }
    $selected = array_values(array_filter(array_map('intval', $selected)));

    if ($newCreditId <= 0) {
        $errors[] = __('Please select a credit.', 'creditalert');
    }
    if (empty($selected)) {
        $errors[] = __('No consumption selected.', 'creditalert');
    }

    if (empty($errors)) {
        $where = [
            "$creditTable.$fieldEntity" => $entityScope,
            'id' => $newCreditId,
        ];
        if ($fieldActive !== '') {
            $where["$creditTable.$fieldActive"] = 1;
        }
        if ($fieldEnd !== '') {
            $where['OR'] = [
                "$creditTable.$fieldEnd" => null,
                new QueryExpression(
                    sprintf(
                        'NOW() < %s',
                        $DB->quoteName("$creditTable.$fieldEnd")
                    )
                ),
            ];
        }

        $creditExists = $DB->request([
            'COUNT' => 'count',
            'FROM'  => $creditTable,
            'WHERE' => $where,
        ])->current();

        if ((int) ($creditExists['count'] ?? 0) === 0) {
            $errors[] = __('Target credit not found.', 'creditalert');
        } else {
            $result = $DB->update(
                $consumptionTable,
                [$fieldFk => $newCreditId],
                [
                    "$consumptionTable.id" => $selected,
                    "$consumptionTable.$fieldTicket" => $ticketId,
                ]
            );

            if ($result) {
                Session::addMessageAfterRedirect(__('Credit reassigned.', 'creditalert'), true, INFO);
                Html::redirect($baseUrl);
            } else {
                $errors[] = __('Reassign failed.', 'creditalert');
            }
        }
    }
}

Html::header(
    __('Reaffecter credit', 'creditalert'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'ticket'
);

Html::displayMessageAfterRedirect();

echo "<div class='card'><div class='card-body'>";
echo "<div class='mb-3'><strong>Ticket</strong> " . (int) $ticketId . " - " . Html::entities_deep($ticket->getName()) . "</div>";

if (!empty($errors)) {
    echo "<div class='alert alert-danger'>";
    foreach ($errors as $error) {
        echo "<div>" . Html::entities_deep($error) . "</div>";
    }
    echo "</div>";
}

$rows = [];
foreach ($DB->request([
    'SELECT' => [
        "$consumptionTable.id AS id",
        "$consumptionTable.$fieldFk AS credit_id",
        "$consumptionTable.$fieldUsed AS consumed",
        "$consumptionTable.date_creation AS consume_date",
        "$consumptionTable.users_id AS users_id",
        "$creditTable.$fieldClient AS credit_label",
    ],
    'FROM'   => $consumptionTable,
    'LEFT JOIN' => [
        $creditTable => [
            'ON' => [
                $consumptionTable => $fieldFk,
                $creditTable => 'id',
            ],
        ],
    ],
    'WHERE' => [
        "$consumptionTable.$fieldTicket" => $ticketId,
    ],
    'ORDER' => [
        "$consumptionTable.id DESC",
    ],
]) as $row) {
    $rows[] = $row;
}

if (empty($rows)) {
    echo "<div class='alert alert-warning'>" . __('No consumption found for this ticket.', 'creditalert') . "</div>";
    echo "</div></div>";
    Html::footer();
    exit;
}

echo "<form method='post' action='" . Html::cleanInputText($baseUrl) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
echo Html::hidden('tickets_id', ['value' => $ticketId]);
if (!empty($_REQUEST['_in_modal'])) {
    echo Html::hidden('_in_modal', ['value' => 1]);
}

echo "<div class='table-responsive mb-3'>";
echo "<table class='table table-hover'>";
echo "<thead><tr>";
echo "<th style='width: 30px;'><input type='checkbox' id='creditalert_check_all' checked></th>";
echo "<th>" . __('Credit', 'creditalert') . "</th>";
echo "<th>" . __('Date de consommation', 'creditalert') . "</th>";
echo "<th>" . __('User') . "</th>";
echo "<th class='text-end'>" . __('Quantite consommee', 'creditalert') . "</th>";
echo "</tr></thead><tbody>";

$showuserlink = Session::haveRight('user', READ) ? 1 : 0;
foreach ($rows as $row) {
    $creditLabel = $row['credit_label'] ?? '';
    $consumeDate = $row['consume_date'] ?? '';
    $userId = (int) ($row['users_id'] ?? 0);
    $consumed = $row['consumed'] ?? 0;
    $rowId = (int) ($row['id'] ?? 0);

    echo "<tr>";
    echo "<td><input type='checkbox' class='creditalert-consumption-check' name='consumption_ids[]' value='{$rowId}' checked></td>";
    echo "<td>" . Html::entities_deep($creditLabel) . "</td>";
    echo "<td>" . Html::convDateTime($consumeDate) . "</td>";
    echo "<td>" . getUserName($userId, $showuserlink) . "</td>";
    echo "<td class='text-end'>" . Html::entities_deep((float) $consumed) . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";
echo "</div>";

$beginField = '';
foreach (['begin_date', 'date_begin', 'start_date'] as $candidate) {
    if ($DB->fieldExists($creditTable, $candidate)) {
        $beginField = $candidate;
        break;
    }
}

$select = [
    "$creditTable.id AS id",
    "$creditTable.$fieldClient AS name",
    "$creditTable.$fieldSold AS quantity_sold",
    "$creditTable.$fieldEntity AS credit_entity_id",
    new QueryExpression("COALESCE(SUM($consumptionTable.$fieldUsed), 0) AS consumed"),
];
$groupby = [
    "$creditTable.id",
    "$creditTable.$fieldClient",
    "$creditTable.$fieldSold",
    "$creditTable.$fieldEntity",
];
if ($fieldActive !== '') {
    $select[] = "$creditTable.$fieldActive AS is_active";
    $groupby[] = "$creditTable.$fieldActive";
}
if ($fieldEnd !== '') {
    $select[] = "$creditTable.$fieldEnd AS end_date";
    $groupby[] = "$creditTable.$fieldEnd";
}
if ($beginField !== '') {
    $select[] = "$creditTable.$beginField AS begin_date";
    $groupby[] = "$creditTable.$beginField";
}

$where = [];
if (!empty($entityScope)) {
    $where["$creditTable.$fieldEntity"] = $entityScope;
}
if ($fieldActive !== '') {
    $where["$creditTable.$fieldActive"] = 1;
}
if ($fieldEnd !== '') {
    $where['OR'] = [
        "$creditTable.$fieldEnd" => null,
        new QueryExpression(
            sprintf(
                'NOW() < %s',
                $DB->quoteName("$creditTable.$fieldEnd")
            )
        ),
    ];
}

$credits = [];
$entityLabels = [];
foreach ($DB->request([
    'SELECT' => $select,
    'FROM'   => $creditTable,
    'LEFT JOIN' => [
        $consumptionTable => [
            'ON' => [
                $consumptionTable => $fieldFk,
                $creditTable => 'id',
            ],
        ],
    ],
    'WHERE' => $where,
    'GROUPBY' => $groupby,
    'ORDER' => ["$creditTable.$fieldClient"],
]) as $row) {
    $active = true;
    if ($fieldActive !== '') {
        $active = ((int) ($row['is_active'] ?? 0)) === 1;
    }
    $expired = false;
    if ($fieldEnd !== '' && !empty($row['end_date'])) {
        $ts = strtotime((string) $row['end_date']);
        if ($ts !== false && $ts < time()) {
            $expired = true;
        }
    }
    if (!$active || $expired) {
        continue;
    }

    $fullEntityLabel = '';
    $creditEntityId = (int) ($row['credit_entity_id'] ?? 0);
    if ($creditEntityId > 0) {
        if (!array_key_exists($creditEntityId, $entityLabels)) {
            $entityLabels[$creditEntityId] = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
                Dropdown::getDropdownName('glpi_entities', $creditEntityId)
            );
        }
        $fullEntityLabel = (string) $entityLabels[$creditEntityId];
    }
    $entityLabel = $fullEntityLabel !== ''
        ? $getRelativeEntityLabel($fullEntityLabel, $selectedEntityParts)
        : '';

    $beginYear = '';
    if ($beginField !== '' && !empty($row['begin_date'])) {
        $ts = strtotime((string) $row['begin_date']);
        if ($ts !== false) {
            $beginYear = date('Y', $ts);
        }
    }

    $percent = '';
    $sold = (float) ($row['quantity_sold'] ?? 0);
    $consumed = (float) ($row['consumed'] ?? 0);
    if ($sold > 0) {
        $value = ($consumed / $sold) * 100;
        $percent = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    $credits[(int) $row['id']] = [
        'label'        => $row['name'],
        'entity_label' => $entityLabel,
        'begin_year'   => $beginYear,
        'percent'      => $percent,
    ];
}

echo "<div class='mb-3'>";
echo "<label class='form-label mb-1'>" . __('Nouveau credit', 'creditalert') . "</label>";
$selectId = 'creditalert_new_credit_' . mt_rand();
$selectIdClean = Html::cleanId($selectId);
echo "<select name='new_credit_id' id='{$selectIdClean}' class='form-select' style='width: 100%'>";
echo "<option value=''>" . Dropdown::EMPTY_VALUE . "</option>";
foreach ($credits as $creditId => $credit) {
    $attr = '';
    if (!empty($credit['entity_label'])) {
        $attr .= " data-entity-label='" . Html::cleanInputText((string) $credit['entity_label']) . "'";
    }
    if ($credit['begin_year'] !== '') {
        $attr .= " data-begin-year='" . Html::cleanInputText((string) $credit['begin_year']) . "'";
    }
    if ($credit['percent'] !== '') {
        $attr .= " data-percent='" . Html::cleanInputText((string) $credit['percent']) . "'";
    }
    echo "<option value='" . (int) $creditId . "'{$attr}>";
    echo Html::entities_deep($credit['label']);
    echo "</option>";
}
echo "</select>";
if (empty($credits)) {
    echo "<div class='alert alert-warning mt-2'>" . __('No active credit found for ticket entity.', 'creditalert') . "</div>";
}
echo "</div>";

$labelBegin = json_encode(__('Debut', 'creditalert'));
$labelConsumed = json_encode(__('Consomme', 'creditalert'));
$js = <<<JS
window.creditalertReassignCreditResult = function(item) {
    if (!item.id) {
        return item.text;
    }
    var data = item.element && item.element.dataset ? item.element.dataset : {};
    var container = $('<span></span>');
    container.text(item.text);
    var addBadge = function(text, cls) {
        var badge = $('<span></span>');
        badge.addClass('badge ms-2 ' + cls);
        badge.css('color', '#ffffff');
        badge.text(text);
        container.append(' ').append(badge);
    };
    if (data.entityLabel) {
        addBadge(data.entityLabel, 'bg-info');
    }
    if (data.beginYear) {
        addBadge({$labelBegin} + ' ' + data.beginYear, 'bg-primary');
    }
    if (data.percent !== undefined && data.percent !== '') {
        addBadge({$labelConsumed} + ' ' + data.percent + '%', 'bg-dark');
    }
    return container;
};

window.creditalertReassignCreditSelection = function(item) {
    return creditalertReassignCreditResult(item);
};
JS;
echo Html::scriptBlock($js);
echo Html::jsAdaptDropdown($selectIdClean, [
    'width'             => '100%',
    'templateResult'    => 'creditalertReassignCreditResult',
    'templateSelection' => 'creditalertReassignCreditSelection',
]);

echo "<div class='text-end'>";
echo Html::submit(__('Reaffecter credit', 'creditalert'), ['name' => 'reassign', 'class' => 'btn btn-primary']);
echo "</div>";
echo "</form>";

echo "</div></div>";

$js = <<<JS
(function() {
    var checkAll = document.getElementById('creditalert_check_all');
    if (!checkAll) {
        return;
    }
    checkAll.addEventListener('change', function() {
        var checks = document.querySelectorAll('.creditalert-consumption-check');
        checks.forEach(function(input) {
            input.checked = checkAll.checked;
        });
    });
})();
JS;
echo Html::scriptBlock($js);

Html::footer();
