<?php

class PluginCreditalertCreditItem extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;
    public $table = 'glpi_plugin_creditalert_vcredits';
    public $no_auto_fields = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Alerte de credit', 'Alertes de credit', $nb, 'creditalert');
    }

    public static function getIcon()
    {
        return 'ti ti-alert-octagon';
    }

    public static function getTable($classname = null)
    {
        PluginCreditalertConfig::ensureViews();
        return 'glpi_plugin_creditalert_vcredits';
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);
    }

    public static function canDelete(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $itemtype = self::class;

        $baseId = 1000;

        $tab[] = [
            'id'            => $baseId + 1,
            'table'         => self::getTable(),
            'field'         => 'client_label',
            'name'          => __('Client / Libelle', 'creditalert'),
            'datatype'      => 'text',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 2,
            'table'         => self::getTable(),
            'field'         => 'entities_id',
            'name'          => Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'itemlink_type' => Entity::class,
            'massiveaction' => false,
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 3,
            'table'         => self::getTable(),
            'field'         => 'quantity_sold',
            'name'          => __('Quantite vendue', 'creditalert'),
            'datatype'      => 'number',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 4,
            'table'         => self::getTable(),
            'field'         => 'quantity_used',
            'name'          => __('Quantite consommee', 'creditalert'),
            'datatype'      => 'number',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 5,
            'table'         => self::getTable(),
            'field'         => 'percentage_used',
            'name'          => __('Consommation %', 'creditalert'),
            'datatype'      => 'number',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 6,
            'table'         => self::getTable(),
            'field'         => 'status',
            'name'          => __('Statut', 'creditalert'),
            'datatype'      => 'dropdown',
            'itemtype'      => $itemtype,
            'toadd'         => [
                'OK'       => __('OK', 'creditalert'),
                'WARNING'  => __('Avertissement', 'creditalert'),
                'OVER'     => __('Surconsommation', 'creditalert'),
                'EXPIRED'  => __('Expire', 'creditalert'),
            ],
        ];

        $tab[] = [
            'id'            => $baseId + 7,
            'table'         => self::getTable(),
            'field'         => 'applied_threshold',
            'name'          => __('Seuil %', 'creditalert'),
            'datatype'      => 'number',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 8,
            'table'         => self::getTable(),
            'field'         => 'end_date',
            'name'          => __('Date d\'expiration', 'creditalert'),
            'datatype'      => 'datetime',
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'            => $baseId + 11,
            'table'         => self::getTable(),
            'field'         => 'last_ticket_id',
            'name'          => Ticket::getTypeName(1),
            'datatype'      => 'specific',
            'itemtype'      => $itemtype,
            'massiveaction' => false,
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        $rawValue = $values;
        if (is_array($values)) {
            $rawValue = $values['name'] ?? $values[$field] ?? $values['id'] ?? current($values);
        }
        switch ($field) {
            case 'status':
                $config = PluginCreditalertConfig::getConfig();
                return self::formatStatus((string) $rawValue, $config);
            case 'entities_id':
                return PluginCreditalertConfig::getEntityShortName((int) $rawValue);
            case 'last_ticket_id':
                if ((int) $rawValue <= 0) {
                    return '';
                }
                $ticketId = (int) $rawValue;
                $url = Ticket::getFormURLWithID($ticketId);
                $label = Dropdown::getDropdownName('glpi_tickets', $ticketId);
                return "<a href='{$url}'>" . Html::entities_deep($label ?: $ticketId) . '</a>';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function formatStatus(string $status, array $config): string
    {
        $status = strtoupper($status);
        $class = 'creditalert-status-ok';
        $color = '';

        if ($status === 'WARNING') {
            $class = 'creditalert-status-warning';
            $color = $config['color_warning'] ?? '';
        } elseif ($status === 'OVER' || $status === 'EXPIRED') {
            $class = 'creditalert-status-over';
            $color = $config['color_over'] ?? '';
        }

        $style = $color !== '' ? "style=\"background-color: {$color};\"" : '';
        $labels = [
            'OK'       => __('OK', 'creditalert'),
            'WARNING'  => __('Avertissement', 'creditalert'),
            'OVER'     => __('Surconsommation', 'creditalert'),
            'EXPIRED'  => __('Expire', 'creditalert'),
        ];
        $label = $labels[$status] ?? $status;
        return "<span class='badge $class' data-status='{$status}' {$style}>{$label}</span>";
    }

    /**
     * Inject row coloring script using configured colors.
     *
     * @param array $config
     *
     * @return void
     */
    public static function injectRowColors(array $config): void
    {
        $warning = addslashes($config['color_warning'] ?? '#f7c77d');
        $over = addslashes($config['color_over'] ?? '#f2958a');
        $js = <<<JS
document.querySelectorAll('table.tab_cadre_fixe tbody tr').forEach(function(row) {
    var statusCell = row.querySelector('.creditalert-status-warning, .creditalert-status-over');
    if (!statusCell) {
        return;
    }
    if (statusCell.classList.contains('creditalert-status-warning')) {
        row.classList.add('creditalert-row-warning');
        row.style.setProperty('--creditalert-row-color', '{$warning}');
    } else if (statusCell.classList.contains('creditalert-status-over')) {
        row.classList.add('creditalert-row-over');
        row.style.setProperty('--creditalert-row-color', '{$over}');
    }
});
JS;
        echo "<style>
.creditalert-row-warning { background-color: var(--creditalert-row-color, {$warning}) !important; }
.creditalert-row-over { background-color: var(--creditalert-row-color, {$over}) !important; }
</style>";
        echo Html::scriptBlock($js);
    }
}
