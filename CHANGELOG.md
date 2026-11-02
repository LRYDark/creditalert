# Journal des changements

## 1.2.2 — 2026-11-02

**Après la mise à jour : cliquer « Mettre à jour » dans Configuration → Plugins.**

- Mails texte sans pièce jointe envoyés par la file d'attente de GLPI : plus d'avertissement « Undefined array
  key "attach_documents_to_notifications_for_anonymous" » quand ce réglage manque dans la base de GLPI (vu en
  production le 02/11/2026).
- CI GitHub : syntaxe sur PHP 8.2 à 8.5, installation réelle dans GLPI 11.0.12 (neuve et mise à jour depuis
  la release précédente), chargement de chaque classe, parcours des pages et de leurs onglets.
- **Affichage** : le message « Élément modifié » après l'enregistrement de la configuration affichait du code HTML
  (`<span class="d-flex …">`) au lieu du nom.

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
