<?php

class PluginCreditalertCreditSummary extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;
    public $table = 'glpi_plugin_creditalert_vcredits';
    public $no_auto_fields = true;
    private const SEARCH_BASE = 2000;

    public static function getTypeName($nb = 0)
    {
        return _n('Synthese des credits', 'Syntheses des credits', $nb, 'creditalert');
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_READ);
    }

    public static function getTable($classname = null)
    {
        PluginCreditalertConfig::ensureViews();
        return 'glpi_plugin_creditalert_vcredits';
    }

    public static function getSearchURL($full = true)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $base = $full ? $CFG_GLPI['root_doc'] : '';
        return $base . '/plugins/creditalert/front/creditlist.php?view=credits';
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $itemtype = self::class;
        $baseId = self::SEARCH_BASE;

        // Hidden ID to retrieve credit id in renderers
        $tab[] = [
            'id'            => $baseId,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => 'ID',
            'datatype'      => 'number',
            'massiveaction' => false,
            'nosearch'      => true,
            'nodisplay'     => true,
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 1,
            'table'    => self::getTable(),
            'field'    => 'client_label',
            'name'     => __('Crédit / Libelle', 'creditalert'),
            'datatype' => 'text',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 2,
            'table'         => self::getTable(),
            'field'         => 'entities_id',
            'name'          => Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'itemlink_type' => Entity::class,
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 3,
            'table'    => self::getTable(),
            'field'    => 'quantity_sold',
            'name'     => __('Quantite vendue', 'creditalert'),
            'datatype' => 'number',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 4,
            'table'    => self::getTable(),
            'field'    => 'quantity_used',
            'name'     => __('Quantite consommee', 'creditalert'),
            'datatype' => 'specific',
            'additionalfields' => ['id'],
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 5,
            'table'    => self::getTable(),
            'field'    => 'percentage_used',
            'name'     => __('Consommation %', 'creditalert'),
            'datatype' => 'number',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 6,
            'table'    => self::getTable(),
            'field'    => 'status',
            'name'     => __('Statut', 'creditalert'),
            'datatype' => 'specific',
            'searchtype' => 'equals',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 7,
            'table'    => self::getTable(),
            'field'    => 'end_date',
            'name'     => __('Date d\'expiration', 'creditalert'),
            'datatype' => 'datetime',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'       => $baseId + 8,
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Actif', 'creditalert'),
            'datatype' => 'specific',
            'searchtype' => 'equals',
            'itemtype' => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 9,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('Export CSV', 'creditalert'),
            'datatype'      => 'specific',
            'additionalfields' => ['id'],
            'creditalert_export' => true,
            'massiveaction' => false,
            'nosearch'      => true,
            'itemtype'      => $itemtype,
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        $searchopt = $options['searchopt'] ?? [];
        if (!empty($searchopt['creditalert_export'])) {
            $creditId = $values['additionalfields']['id'] ?? null;
            if (!$creditId) {
                $creditId = self::extractCreditIdFromRaw($options, $values);
            }
            if ($creditId) {
                global $CFG_GLPI;
                $csvUrl  = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/credittickets.php?credit_id=' . (int) $creditId . '&export=csv';
                $exportLabel = __('Export', 'creditalert');
                return "<a href='{$csvUrl}' title='{$exportLabel}'>CSV</a>";
            }
            return '';
        }

        switch ($field) {
            case 'status':
                $status = strtoupper((string) ($values['name'] ?? $values));
                $config = PluginCreditalertConfig::getConfig();
                return PluginCreditalertCreditItem::formatStatus($status, $config);
            case 'is_active':
                $raw = $values['name'] ?? ($values['is_active'] ?? $values);
                $active = ((int) $raw) === 1;
                $label = $active ? __('Actif', 'creditalert') : __('Inactif', 'creditalert');
                $class = $active ? 'bg-success' : 'bg-secondary';
                return "<span class='badge {$class}'>{$label}</span>";
            case 'entities_id':
                $entityId = $values['name'] ?? $values['entities_id'] ?? $values['id'] ?? current($values);
                return PluginCreditalertConfig::getEntityShortName((int) $entityId);
            case 'quantity_used':
                $quantity = $values['name'] ?? ($values['quantity_used'] ?? $values);
                $creditId = $values['additionalfields']['id'] ?? null;
                if (!$creditId) {
                    $creditId = self::extractCreditIdFromRaw($options, $values);
                }

                if ($creditId) {
                    global $CFG_GLPI;
                    $baseUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/credittickets.php?credit_id=' . $creditId;
                    $label   = Html::entities_deep((float) $quantity);

                    $modalId = 'creditalert_tickets_' . $creditId;
                    $modal = Ajax::createIframeModalWindow(
                        $modalId,
                        $baseUrl,
                        [
                            'title'         => __('Consumed tickets', 'creditalert'),
                            'reloadonclose' => false,
                            'display'       => false,
                        ]
                    );
                    $link = "<a href='#' data-bs-toggle='modal' data-bs-target='#{$modalId}' title='" . __('Consumed tickets', 'creditalert') . "'>{$label}</a>";
                    return $modal . $link;
                }

                return Html::entities_deep((float) $quantity);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'status':
                $options['value'] = $values[$field] ?? '';
                $choices = [
                    'OK'      => __('OK', 'creditalert'),
                    'WARNING' => __('Avertissement', 'creditalert'),
                    'OVER'    => __('Surconsommation', 'creditalert'),
                    'EXPIRED' => __('Expire', 'creditalert'),
                ];
                return Dropdown::showFromArray($name, $choices, $options);
            case 'is_active':
                $options['value'] = $values[$field] ?? '';
                $choices = [
                    1 => __('Actif', 'creditalert'),
                    0 => __('Inactif', 'creditalert'),
                ];
                return Dropdown::showFromArray($name, $choices, $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Try multiple raw_data shapes to retrieve credit id for link building.
     */
    private static function extractCreditIdFromRaw(array $options, $values): ?int
    {
        // Sometimes current cell carries id
        if (isset($values['id']) && ctype_digit((string) $values['id'])) {
            return (int) $values['id'];
        }
        if (isset($values['additionalfields']['id']) && ctype_digit((string) $values['additionalfields']['id'])) {
            return (int) $values['additionalfields']['id'];
        }

        // Raw data per search option key
        $rawAll = $options['raw_data'] ?? [];
        $candidateKeys = [
            'ITEM_PluginCreditalertCreditSummary_2000', // hidden id column
            'id',                                      // default id column
        ];

        foreach ($candidateKeys as $key) {
            if (!isset($rawAll[$key][0])) {
                continue;
            }
            $raw = $rawAll[$key][0];
            if (isset($raw['id']) && ctype_digit((string) $raw['id'])) {
                return (int) $raw['id'];
            }
            if (isset($raw['name']) && ctype_digit((string) $raw['name'])) {
                return (int) $raw['name'];
            }
        }
        return null;
    }

    public function prepareInputForAdd($input)
    {
        return false;
    }

    public function prepareInputForUpdate($input)
    {
        return false;
    }

    public function prepareInputForDelete($input)
    {
        return false;
    }
}
