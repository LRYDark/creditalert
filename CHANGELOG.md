# Journal des changements

## 1.2.3 — 2026-10-06

- **La tâche automatique ne bloque plus celles des autres plugins.** Elle passe de la classe
  `PluginCreditalertAlertTask` à `GlpiPlugin\Creditalert\AlertTask`. GLPI reconnaît la tâche d'un plugin au début
  du nom de sa classe, et « PluginCreditalert… » était pris pour une tâche du plugin credit : creditalert désactivé
  ou à mettre à jour, GLPI tentait quand même de la lancer, échouait sans dater son passage, et la reprenait en tête
  de file à chaque minute — les tâches des autres plugins ne passaient plus (printgestion bloqué depuis le
  06/10). Désormais, plugin inactif = tâche ignorée, comme pour tout plugin. La mise à jour migre la tâche en gardant
  ses réglages (état, fréquence, dernier passage).
- **Alertes de crédit envoyées par la file d'attente des notifications de GLPI**, aussitôt, comme avant. Visibles
  dans Administration → File d'attente des notifications, tracées dans les journaux mail de GLPI. Un mail par
  destinataire.
- **Même règle qu'avant pour « notifié »** : un crédit n'est noté notifié que si son alerte est partie. Aucun envoi
  réussi : l'alerte est retirée de la file et rejouée au passage suivant de la tâche (jamais perdue, jamais en
  double). Envoi réussi pour une partie des destinataires seulement : crédit noté, les autres restent en file et
  GLPI les renvoie.
- **Garanties d'envoi** : si la file d'attente est indisponible (écriture refusée, erreur de base), le mail part en
  direct comme avant — la file n'empêche jamais un envoi. Un mail envoyé aussitôt est mis en file avec une heure
  d'envoi décalée de 5 minutes, pour que la tâche « queuednotification » ne l'envoie pas une seconde fois pendant
  l'envoi immédiat.
- Aucune migration de base.
