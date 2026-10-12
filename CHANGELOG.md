# Journal des changements

## 1.3.0 — 2026-10-12

Première version pour GLPI 12 (12.0.x). La série 1.2.x reste celle de GLPI 11.

- **Compatible GLPI 12 uniquement.** GLPI 12 déclare le type des propriétés de ses classes (`$rightname`,
  `$dohistory`…) ; une classe du plugin qui les redéfinit doit porter le même type, ce que GLPI 11 refuse. Les deux
  séries ne sont donc pas interchangeables.
- API retirées par GLPI 12 remplacées : `QueryExpression` global, `Html::entities_deep()` / `cleanInputText()`,
  `Html::displayErrorAndDie()` / `displayRightError()`, `Glpi\Toolbox\Sanitizer`.
- Fonctionne avec Credit 1.16.0 (version GLPI 12 du plugin Credit) : ses tables n'ont pas changé.
- **Plus de jeton CSRF.** GLPI 12 contrôle l'origine de la requête (en-têtes `Sec-Fetch-Site` / `Origin`) : les
  champs `_glpi_csrf_token`, les en-têtes `X-Glpi-Csrf-Token` et les appels à `Session::checkCSRF()` /
  `getNewCSRFToken()` sont retirés.
- Inclusion de `inc/includes.php` retirée (obsolète).
- Aucune migration de base.

## 1.2.1 — 2026-10-09

Une seule mise à jour pour les changements préparés sous 1.2.1, 1.2.2 et 1.2.3 (aucune de ces versions n'a été
publiée) : depuis la 1.2.0, « Mettre à jour » applique tout d'un coup.

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
