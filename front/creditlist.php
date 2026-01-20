<?php

include('../../../inc/includes.php');
/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

Html::header(
    PluginCreditalertCreditItem::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'creditlist'
);

// Tabs: consumptions (view) and credits (view)
$view = $_GET['view'] ?? ($_POST['view'] ?? 'consumptions');

echo "<div class='card mb-3'><div class='card-body'>";
echo "<ul class='nav nav-tabs' role='tablist'>";
$tabs = [
    'consumptions' => __('Consommations par client', 'creditalert'),
    'credits'      => __('Synthese des credits', 'creditalert'),
];
foreach ($tabs as $tabKey => $label) {
    $active = $view === $tabKey ? 'active' : '';
    $url = Html::cleanInputText($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php?view=' . $tabKey);
    echo "<li class='nav-item' role='presentation'>";
    echo "<a class='nav-link $active' href='$url'>" . $label . "</a>";
    echo "</li>";
}
echo "</ul>";
echo "</div></div>";

PluginCreditalertConfig::ensureViews();

if ($view === 'credits') {
    // Use search on SQL view (no cache)
    Search::show(PluginCreditalertCreditSummary::class);
} else {
    $config = PluginCreditalertConfig::getConfig();
    /** @var DBmysql $DB */
    global $DB;

    $entityId = (int) ($_GET['entities_id'] ?? 0);
    $dateBegin = $_GET['date_begin'] ?? '';
    $dateEnd = $_GET['date_end'] ?? '';
    $showOther = !empty($_GET['show_other']);
    $selectedCredits = $_GET['credits_id'] ?? [];
    if (!is_array($selectedCredits)) {
        $selectedCredits = [$selectedCredits];
    }
    $selectedCredits = array_values(array_filter(array_map('intval', $selectedCredits)));

    $entityScope = [];
    if ($entityId > 0) {
        $entityScope = getSonsOf('glpi_entities', $entityId);
        if (!is_array($entityScope)) {
            $entityScope = [$entityId];
        }
        $entityScope = array_values(array_unique(array_filter(array_map('intval', $entityScope))));
        if (empty($entityScope)) {
            $entityScope = [$entityId];
        }
    }

    $creditTable = $config['credit_table'];
    $fieldEntity = $config['field_entity'];
    $fieldClient = $config['field_client'];
    $fieldActive = $config['field_is_active'];
    $creditListTable = $creditTable;
    $creditLabelField = $fieldClient;
    $creditEntityField = $fieldEntity;
    $selectedEntityLabel = '';
    $selectedEntityParts = [];
    if ($entityId > 0) {
        $selectedEntityLabel = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
            Dropdown::getDropdownName('glpi_entities', $entityId)
        );
        $selectedEntityParts = array_values(array_filter(
            array_map('trim', explode('>', $selectedEntityLabel)),
            static function ($part) {
                return $part !== '';
            }
        ));
    }
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

    $creditsOptions = [];
    $creditsStatus = [];
    $creditsEntityDepth = [];
    $creditsEntityLabels = [];
    $creditsBeginLabels = [];
    $entityLabels = [];
    $beginField = '';
    foreach (['begin_date', 'date_begin', 'start_date'] as $candidate) {
        if ($DB->fieldExists($creditListTable, $candidate)) {
            $beginField = $candidate;
            break;
        }
    }
    if ($entityId > 0) {
        $creditEntityScope = $entityScope ?: [$entityId];
        $query = [
            'SELECT' => [
                "$creditListTable.id AS id",
                "$creditListTable.$creditLabelField AS name",
                "$creditListTable.$creditEntityField AS credit_entity_id",
            ],
            'FROM'   => $creditListTable,
            'WHERE'  => [
                "$creditListTable.$creditEntityField" => $creditEntityScope,
            ],
            'ORDER'  => [
                "$creditListTable.$creditLabelField",
            ],
        ];
        if (!empty($fieldActive)) {
            $query['SELECT'][] = "$creditListTable.$fieldActive AS is_active";
        }
        if ($beginField !== '') {
            $query['SELECT'][] = "$creditListTable.$beginField AS begin_date";
        }
        foreach ($DB->request($query) as $row) {
            $creditId = (int) ($row['id'] ?? 0);
            if ($creditId <= 0) {
                continue;
            }
            $creditName = (string) ($row['name'] ?? '');
            $creditEntityId = (int) ($row['credit_entity_id'] ?? 0);
            $fullEntityLabel = '';
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
            $optionLabel = trim($creditName);
            if ($optionLabel === '') {
                $optionLabel = $entityLabel;
            }
            $depth = $fullEntityLabel === '' ? PHP_INT_MAX : substr_count($fullEntityLabel, '>');
            if (!isset($creditsOptions[$creditId]) || $depth < ($creditsEntityDepth[$creditId] ?? PHP_INT_MAX)) {
                $creditsOptions[$creditId] = $optionLabel;
                $creditsEntityDepth[$creditId] = $depth;
                $creditsEntityLabels[$creditId] = $entityLabel;
            }
            if (!empty($fieldActive)) {
                if (!isset($creditsStatus[$creditId])) {
                    $creditsStatus[$creditId] = ((int) ($row['is_active'] ?? 0)) === 1 ? 'active' : 'inactive';
                }
            } else {
                if (!isset($creditsStatus[$creditId])) {
                    $creditsStatus[$creditId] = 'active';
                }
            }
            if ($beginField !== '' && !empty($row['begin_date']) && !isset($creditsBeginLabels[$creditId])) {
                $ts = strtotime((string) $row['begin_date']);
                if ($ts !== false) {
                    $creditsBeginLabels[$creditId] = date('m/Y', $ts);
                }
            }
        }
    }

    echo "<form method='get' action='" . $CFG_GLPI['root_doc'] . "/plugins/creditalert/front/creditlist.php' class='mb-3'>";
    echo Html::hidden('view', ['value' => 'consumptions']);
    echo "<div class='card mb-3'><div class='card-body'>";
    echo "<div class='row g-3 align-items-end'>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Entite', 'creditalert') . "</label>";
    Dropdown::show('Entity', [
        'name'      => 'entities_id',
        'value'     => $entityId,
        'on_change' => 'this.form.submit();',
    ]);
    echo "</div>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Date de debut', 'creditalert') . "</label>";
    Html::showDateField('date_begin', [
        'value'       => $dateBegin,
        'placeholder' => __('Date de debut', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Date de fin', 'creditalert') . "</label>";
    Html::showDateField('date_end', [
        'value'       => $dateEnd,
        'placeholder' => __('Date de fin', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";

    echo "</div>";
    echo "<div class='row g-3'>";
    echo "<div class='col-12 creditalert-credits-row'>";
    echo "<label class='form-label mb-1'>" . __('Credits', 'creditalert') . "</label>";
    $creditSelectRand = mt_rand();
    static $creditFilterScriptLoaded = false;
    if (!$creditFilterScriptLoaded) {
        $creditFilterScriptLoaded = true;
        $activeLabel = json_encode(__('Actif', 'creditalert'));
        $inactiveLabel = json_encode(__('Inactif', 'creditalert'));
        $beginLabel = json_encode(__('Date debut', 'creditalert'));
        $js = <<<JS
window.creditalertCreditFilterResult = function(item) {
    if (!item.id) {
        return item.text;
    }
    var status = '';
    if (item.element && item.element.dataset && item.element.dataset.status) {
        status = item.element.dataset.status;
    }
    var entityLabel = '';
    if (item.element && item.element.dataset && item.element.dataset.entityLabel) {
        entityLabel = item.element.dataset.entityLabel;
    }
    var beginLabelValue = '';
    if (item.element && item.element.dataset && item.element.dataset.beginLabel) {
        beginLabelValue = item.element.dataset.beginLabel;
    }
    var container = $('<span></span>');
    container.text(item.text);
    var addBadge = function(text, style) {
        var badge = $('<span></span>');
        badge.addClass('badge ms-2');
        if (style) {
            badge.attr('style', style);
        }
        badge.text(text);
        container.append(' ').append(badge);
    };
    if (entityLabel) {
        addBadge(entityLabel, 'background-color:#ffffff;color:#0d6efd;border:1px solid #0d6efd;');
    }
    if (status === 'inactive') {
        addBadge({$inactiveLabel}, 'background-color:#ffffff;color:#6c757d;border:1px solid #6c757d;');
    } else if (status === 'active') {
        addBadge({$activeLabel}, 'background-color:#ffffff;color:#1a7f37;border:1px solid #1a7f37;');
    }
    if (beginLabelValue) {
        addBadge({$beginLabel} + ' : ' + beginLabelValue, 'background-color:#ffffff;color:#343a40;border:1px solid #343a40;');
    }
    return container;
};

window.creditalertCreditFilterSelection = function(item) {
    return creditalertCreditFilterResult(item);
};
JS;
        echo Html::scriptBlock($js);
    }
    Dropdown::showFromArray('credits_id', $creditsOptions, [
        'multiple' => true,
        'values'   => $selectedCredits,
        'disabled' => empty($creditsOptions),
        'width'    => '100%',
        'rand'     => $creditSelectRand,
        'templateResult' => 'creditalertCreditFilterResult',
        'templateSelection' => 'creditalertCreditFilterSelection',
    ]);
    if (!empty($creditsStatus) || !empty($creditsEntityLabels) || !empty($creditsBeginLabels)) {
        $selectId = 'dropdown_credits_id' . $creditSelectRand;
        $statusJson = json_encode((object) $creditsStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $entityJson = json_encode((object) $creditsEntityLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $beginJson = json_encode((object) $creditsBeginLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $js = <<<JS
(function() {
    var select = document.getElementById('{$selectId}');
    if (!select) {
        return;
    }
    var statuses = {$statusJson};
    var entities = {$entityJson};
    var begins = {$beginJson};
    Object.keys(statuses).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.status = statuses[id];
        }
    });
    Object.keys(entities).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.entityLabel = entities[id];
        }
    });
    Object.keys(begins).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.beginLabel = begins[id];
        }
    });
})();
JS;
        echo Html::scriptBlock($js);
    }
    echo "</div>";

    echo "</div>";
    echo "<div class='row g-3'>";
    echo "<div class='col-md-6'>";
    echo "<div class='form-check mt-2'>";
    echo "<input class='form-check-input' type='checkbox' name='show_other' id='creditalert_show_other' value='1'"
        . ($showOther ? ' checked' : '') . ">";
    echo "<label class='form-check-label' for='creditalert_show_other'>"
        . __('Afficher autres tickets', 'creditalert') . "</label>";
    echo "</div>";
    echo "</div>";

    echo "</div>";
    echo "<div class='mt-3 text-end'>";
    echo Html::submit(__('Rechercher', 'creditalert'), ['name' => 'search', 'class' => 'btn btn-primary']);
    echo "</div>";
    echo "</div></div>";
    Html::closeForm();

    if (isset($_GET['search'])) {
        if ($entityId <= 0 || (empty($selectedCredits) && !$showOther)) {
            echo "<div class='alert alert-warning'>" . __('Veuillez selectionner une entite et au moins un credit.', 'creditalert') . "</div>";
        } else {
            $searchParams = Search::manageParams(PluginCreditalertConsumption::class, $_GET);
            $userCriteria = $searchParams['criteria'] ?? [];

            $searchFormParams = $searchParams;
            $searchFormParams['target'] = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php?view=consumptions';
            $searchFormParams['addhidden'] = [
                'view'        => 'consumptions',
                'entities_id' => $entityId,
                'date_begin'  => $dateBegin,
                'date_end'    => $dateEnd,
                'show_other'  => $showOther ? 1 : 0,
                'credits_id'  => $selectedCredits,
            ];
            Search::showGenericSearch(PluginCreditalertConsumption::class, $searchFormParams);

            $criteria = $userCriteria;

            $entityCriteria = [];
            $scopeIds = $entityScope ?: [$entityId];
            foreach ($scopeIds as $scopeId) {
                $entityCriteria[] = [
                    'link'       => 'OR',
                    'field'      => PluginCreditalertConsumption::OPT_ENTITY,
                    'searchtype' => 'equals',
                    'value'      => $scopeId,
                    '_hidden'    => true,
                ];
            }
            $criteria[] = [
                'link'     => 'AND',
                'criteria' => $entityCriteria,
                '_hidden'  => true,
            ];

            if (!empty($selectedCredits) || $showOther) {
                $creditCriteria = [];
                foreach ($selectedCredits as $creditId) {
                    $creditCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_CREDIT_ID,
                        'searchtype'=> 'equals',
                        'value'     => $creditId,
                        '_hidden'   => true,
                        'virtual'   => true,
                    ];
                }
                if ($showOther) {
                    $creditCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_HAS_CONSUMPTION,
                        'searchtype'=> 'equals',
                        'value'     => 0,
                        '_hidden'   => true,
                        'virtual'   => true,
                    ];
                }
                $criteria[] = [
                    'link'     => 'AND',
                    'criteria' => $creditCriteria,
                    '_hidden'  => true,
                ];
            }

            if ($dateBegin !== '' || $dateEnd !== '') {
                $dateCriteria = [];
                $startValue = $dateBegin !== '' ? $dateBegin . ' 00:00:00' : '';
                $endValue = $dateEnd !== '' ? $dateEnd . ' 23:59:59' : '';

                if ($startValue !== '' && $endValue !== '') {
                    $openGroup = [
                        'link'     => 'OR',
                        'criteria' => [
                            [
                                'link'      => 'AND',
                                'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                                'searchtype'=> 'morethan',
                                'value'     => $startValue,
                                '_hidden'   => true,
                            ],
                            [
                                'link'      => 'AND',
                                'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                                'searchtype'=> 'lessthan',
                                'value'     => $endValue,
                                '_hidden'   => true,
                            ],
                        ],
                        '_hidden'  => true,
                    ];
                    $endGroup = [
                        'link'     => 'OR',
                        'criteria' => [
                            [
                                'link'      => 'AND',
                                'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                                'searchtype'=> 'morethan',
                                'value'     => $startValue,
                                '_hidden'   => true,
                            ],
                            [
                                'link'      => 'AND',
                                'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                                'searchtype'=> 'lessthan',
                                'value'     => $endValue,
                                '_hidden'   => true,
                            ],
                        ],
                        '_hidden'  => true,
                    ];
                    $dateCriteria[] = $openGroup;
                    $dateCriteria[] = $endGroup;
                } elseif ($startValue !== '') {
                    $dateCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                        'searchtype'=> 'morethan',
                        'value'     => $startValue,
                        '_hidden'   => true,
                    ];
                    $dateCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                        'searchtype'=> 'morethan',
                        'value'     => $startValue,
                        '_hidden'   => true,
                    ];
                } elseif ($endValue !== '') {
                    $dateCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                        'searchtype'=> 'lessthan',
                        'value'     => $endValue,
                        '_hidden'   => true,
                    ];
                    $dateCriteria[] = [
                        'link'      => 'OR',
                        'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                        'searchtype'=> 'lessthan',
                        'value'     => $endValue,
                        '_hidden'   => true,
                    ];
                }

                if (!empty($dateCriteria)) {
                    $criteria[] = [
                        'link'     => 'AND',
                        'criteria' => $dateCriteria,
                        '_hidden'  => true,
                    ];
                }
            }

            $searchParams['criteria'] = $criteria;
            $searchParams['hide_controls'] = false;
            $searchParams['hide_criteria'] = true;

            $targetParams = [
                'view'       => 'consumptions',
                'search'     => 1,
                'entities_id'=> $entityId,
                'date_begin' => $dateBegin,
                'date_end'   => $dateEnd,
                'show_other' => $showOther ? 1 : 0,
            ];
            foreach ($selectedCredits as $cid) {
                $targetParams['credits_id'][] = $cid;
            }
            $searchParams['target'] = $CFG_GLPI['root_doc']
                . '/plugins/creditalert/front/creditlist.php?' . http_build_query($targetParams);

            $specificActions = [];
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'reassigncredit']
                    = __('Reaffecter credit', 'creditalert');
            }
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'exportcsv']
                    = __('Exporter CSV', 'creditalert');
            }

            $searchParams['showmassiveactions'] = !empty($specificActions);
            $searchParams['massiveactionparams'] = [
                'specific_actions' => $specificActions,
                'extraparams'      => [
                    'entities_id' => $entityId,
                    'hidden' => [
                        'entities_id' => $entityId,
                        'show_other'  => $showOther ? 1 : 0,
                        'redirect'    => $searchParams['target'],
                    ],
                ],
            ];

            $forcedDisplay = [
                PluginCreditalertConsumption::OPT_TICKET,
                PluginCreditalertConsumption::OPT_CREDIT_LABEL,
                PluginCreditalertConsumption::OPT_ENTITY,
                PluginCreditalertConsumption::OPT_CONSUME_DATE,
                PluginCreditalertConsumption::OPT_CONSUMED,
                PluginCreditalertConsumption::OPT_TICKET_STATUS,
                PluginCreditalertConsumption::OPT_TICKET_DATE,
            ];

            $data = Search::getDatas(PluginCreditalertConsumption::class, $searchParams, $forcedDisplay);
            $allowedColumns = array_values(array_unique(array_map('intval', $forcedDisplay)));
            if (isset($data['data']['cols'])) {
                $data['data']['cols'] = array_values(array_filter(
                    $data['data']['cols'],
                    static function ($col) use ($allowedColumns) {
                        return in_array((int) $col['id'], $allowedColumns, true);
                    }
                ));
            }
            switch ($data['display_type']) {
                case Search::CSV_OUTPUT:
                case Search::PDF_OUTPUT_LANDSCAPE:
                case Search::PDF_OUTPUT_PORTRAIT:
                case Search::SYLK_OUTPUT:
                case Search::NAMES_OUTPUT:
                    Search::outputData($data);
                    break;
                case Search::GLOBAL_SEARCH:
                case Search::HTML_OUTPUT:
                default:
                    Search::displayData($data);
                    break;
            }
            if ($showOther) {
                PluginCreditalertConsumption::injectOtherTicketRowColors();
            }

            if (isset($_GET['creditalert_export']) && (int) $_GET['creditalert_export'] === 1) {
                $exportUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/consumptions.export.php';
                echo "<iframe src='" . Html::cleanInputText($exportUrl) . "' style='display:none' aria-hidden='true'></iframe>";
                $js = <<<JS
(function() {
  try {
    var url = new URL(window.location.href);
    if (url.searchParams.has('creditalert_export')) {
      url.searchParams.delete('creditalert_export');
      window.history.replaceState({}, document.title, url.toString());
    }
  } catch (e) {}
})();
JS;
                echo Html::scriptBlock($js);
            }
        }
    }
}

Html::footer();
