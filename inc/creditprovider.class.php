<?php

use Glpi\DBAL\QueryExpression;

class PluginCreditalertCreditProvider
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = array_merge(PluginCreditalertConfig::getDefaultConfig(), $config);
    }

    /**
     * Fetch raw credit rows from the credit plugin tables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCredits(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $cfg = $this->config;
        $creditTable = $cfg['credit_table'];
        $consumptionTable = $cfg['consumption_table'];
        $usedField = $cfg['field_used'];

        $select = [
            "$creditTable.id AS source_item_id",
            "$creditTable.{$cfg['field_entity']} AS entities_id",
            "$creditTable.{$cfg['field_client']} AS client_label",
            "$creditTable.{$cfg['field_sold']} AS quantity_sold",
        ];

        if (!empty($cfg['field_end_date'])) {
            $select[] = "$creditTable.{$cfg['field_end_date']} AS end_date";
        }

        $select[] = new QueryExpression(
            sprintf(
                'COALESCE(SUM(%s), 0) AS quantity_used',
                $DB->quoteName($consumptionTable . '.' . $usedField)
            )
        );

        if (!empty($cfg['field_ticket'])) {
            $select[] = new QueryExpression(
                sprintf(
                    'MAX(%s) AS last_ticket_id',
                    $DB->quoteName($consumptionTable . '.' . $cfg['field_ticket'])
                )
            );
        }

        $query = [
            'SELECT'    => $select,
            'FROM'      => $creditTable,
            'LEFT JOIN' => [
                $consumptionTable => [
                    'ON' => [
                        $consumptionTable => $cfg['field_fk_usage'],
                        $creditTable      => 'id',
                    ],
                ],
            ],
            'GROUPBY'   => "$creditTable.id",
        ];

        if (!empty($cfg['field_is_active'])) {
            $query['WHERE'] = [
                $creditTable . '.' . $cfg['field_is_active'] => 1,
            ];
        }

        $rows = [];
        foreach ($DB->request($query) as $data) {
            $rows[] = $data;
        }

        return $rows;
    }
}
