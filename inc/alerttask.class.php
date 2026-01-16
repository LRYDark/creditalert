<?php

use Glpi\DBAL\QueryExpression;

if (!class_exists(QueryExpression::class) && class_exists('\QueryExpression')) {
    class_alias('\QueryExpression', QueryExpression::class);
}

class PluginCreditalertAlertTask extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;
    private const NOTIFICATION_TABLE = 'glpi_plugin_creditalert_notifications';

    public static function cronInfo($name)
    {
        if ($name === 'creditalert') {
            return [
                'description' => __('Scan credits and notify when thresholds are reached', 'creditalert'),
            ];
        }
        return [];
    }

    public static function cronCreditalert(CronTask $task)
    {
        return self::runAlerts($task);
    }

    /**
     * Manual runner to trigger the alert scan.
     *
     * @param CronTask|null $task
     *
     * @return int
     */
    public static function process(CronTask $task = null): int
    {
        return self::runAlerts($task);
    }

    private static function runAlerts(?CronTask $task): int
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::NOTIFICATION_TABLE)) {
            return 1;
        }

        PluginCreditalertConfig::ensureViews();

        $rows = $DB->request([
            'SELECT' => [
                'id',
                'entities_id',
                'client_label',
                'quantity_sold',
                'quantity_used',
                'percentage_used',
                'applied_threshold',
                'status',
                'end_date',
            ],
            'FROM'   => 'glpi_plugin_creditalert_vcredits',
            'WHERE'  => ['status' => ['WARNING', 'OVER']],
        ]);

        $sent = 0;
        foreach ($rows as $credit) {
            $recipients = PluginCreditalertConfig::getRecipientsForEntity((int) $credit['entities_id']);
            if (empty($recipients)) {
                continue;
            }

            $hash = self::buildHash($credit);
            $previous = self::getLastNotification((int) $credit['id']);
            if ($previous && $previous['last_hash'] === $hash) {
                continue;
            }

            $percentage = (float) $credit['percentage_used'];
            $threshold  = (int) ($credit['applied_threshold'] ?? 0);
            if (self::sendNotification($credit, (string) $credit['status'], $percentage, $threshold, $recipients)) {
                self::storeNotification((int) $credit['id'], (string) $credit['status'], $percentage, $hash);
                $sent++;
            }
        }

        if ($task) {
            $task->addVolume($sent);
        }

        return 1;
    }

    private static function buildHash(array $credit): string
    {
        return sha1(json_encode([
            'id'         => (int) $credit['id'],
            'status'     => (string) $credit['status'],
            'percentage' => (float) $credit['percentage_used'],
            'used'       => (float) $credit['quantity_used'],
            'threshold'  => (float) ($credit['applied_threshold'] ?? 0),
            'end_date'   => $credit['end_date'] ?? '',
        ]));
    }

    private static function getLastNotification(int $creditId): ?array
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::NOTIFICATION_TABLE,
            'WHERE' => ['plugin_credit_entities_id' => $creditId],
            'LIMIT' => 1,
        ]);

        $row = $iterator->current();
        return $row ?: null;
    }

    private static function storeNotification(int $creditId, string $status, float $percentage, string $hash): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $existing = self::getLastNotification($creditId);
        if ($existing) {
            $DB->update(
                self::NOTIFICATION_TABLE,
                [
                    'last_status'      => $status,
                    'last_percentage'  => $percentage,
                    'last_hash'        => $hash,
                    'last_notified_at' => new QueryExpression('CURRENT_TIMESTAMP'),
                ],
                ['plugin_credit_entities_id' => $creditId]
            );
            return;
        }

        $DB->insert(
            self::NOTIFICATION_TABLE,
            [
                'plugin_credit_entities_id' => $creditId,
                'last_status'               => $status,
                'last_percentage'           => $percentage,
                'last_hash'                 => $hash,
                'last_notified_at'          => new QueryExpression('CURRENT_TIMESTAMP'),
            ]
        );
    }

    private static function sendNotification(
        array $credit,
        string $status,
        float $percentage,
        int $threshold,
        array $recipients
    ): bool {
        global $CFG_GLPI;

        if (empty($recipients)) {
            return false;
        }

        $labels = [
            'OK'       => __('OK', 'creditalert'),
            'WARNING'  => __('Avertissement', 'creditalert'),
            'OVER'     => __('Surconsommation', 'creditalert'),
            'EXPIRED'  => __('Expire', 'creditalert'),
        ];
        $statusLabel = $labels[strtoupper($status)] ?? $status;

        $entityLabel = Dropdown::getDropdownName('glpi_entities', $credit['entities_id']);
        $subject = sprintf(
            '[CreditAlert] %s - %s',
            $statusLabel,
            $credit['client_label']
        );

        $bodyLines = [
            sprintf(__('Client : %s', 'creditalert'), $credit['client_label']),
            sprintf(__('Entite : %s', 'creditalert'), $entityLabel),
            sprintf(__('Quantite vendue : %s', 'creditalert'), $credit['quantity_sold']),
            sprintf(__('Quantite consommee : %s', 'creditalert'), $credit['quantity_used']),
            sprintf(__('Consommation : %s%% (seuil %s%%)', 'creditalert'), $percentage, $threshold),
            sprintf(__('Statut : %s', 'creditalert'), $statusLabel),
        ];

        if (!empty($credit['end_date'])) {
            $bodyLines[] = sprintf(__('Date de fin : %s', 'creditalert'), $credit['end_date']);
        }

        $mailer = new GLPIMailer();
        $mailer->setSubject($subject);
        $mailer->setBody(implode(PHP_EOL, $bodyLines));

        foreach ($recipients as $email) {
            $mailer->addTo($email);
        }

        if (!empty($CFG_GLPI['admin_email'])) {
            $mailer->setFrom($CFG_GLPI['admin_email']);
        }

        return $mailer->send();
    }
}
