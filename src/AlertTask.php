<?php

namespace GlpiPlugin\Creditalert;

use CronTask;
use PluginCreditalertAlertTask;

/**
 * Tâche automatique « creditalert », sous l'espace de noms du plugin.
 *
 * GLPI ne lance que les tâches des plugins actifs, qu'il reconnaît au début du nom
 * de leur classe : « PluginCredit… » pour le plugin credit. L'ancienne classe de la
 * tâche, PluginCreditalertAlertTask, commence de même : creditalert inactif (désactivé
 * ou à mettre à jour), GLPI la prenait pour une tâche de credit, actif, et tentait de
 * la lancer sans pouvoir la charger. L'échec ne datant pas son dernier passage, elle
 * restait en tête de file et prenait la place des tâches des autres plugins.
 *
 * « GlpiPlugin\Creditalert\… » n'appartient qu'à ce plugin : inactif, sa tâche est
 * ignorée par GLPI, comme celles de tout plugin inactif. Le traitement reste dans
 * PluginCreditalertAlertTask.
 */
class AlertTask
{
    public static function cronInfo($name)
    {
        return PluginCreditalertAlertTask::cronInfo($name);
    }

    public static function cronCreditalert(CronTask $task)
    {
        return PluginCreditalertAlertTask::cronCreditalert($task);
    }
}
