# Journal des changements

## 1.3.2 — 2026-11-06

**Après la mise à jour : cliquer « Mettre à jour » dans Configuration → Plugins.**

- **Nouveau nom : « Gestion des crédits »** dans la liste des plugins, le menu Outils, le titre de la page et l'onglet
  des droits du profil : le plugin fait plus que des alertes (synthèse, consommations, exports). L'onglet
  « Credit Alert » devient « Alertes de crédit ». La clé technique reste `creditalert` (dossier, tables, droits) :
  rien à réinstaller, rien de perdu. Le sujet des mails garde `[CreditAlert]` (règles de messagerie existantes).
- **Dates du crédit imputé dans les exports** : deux colonnes en fin de fichier, « Date de début du crédit » et
  « Date de fin du crédit » (le jour, AAAA-MM-JJ), dans l'export de toutes les pages, l'export d'une sélection (XLSX
  ou CSV) et l'export CSV d'un crédit. Les colonnes existantes ne bougent pas. Un ticket imputé sur deux crédits a
  une ligne par crédit, chacune avec les dates de son crédit ; cases vides pour un ticket sans crédit.
- Ces deux dates sont aussi des colonnes à afficher et des critères de recherche dans la liste « Consommations par
  client ». La vue SQL des consommations les ajoute ; le plugin la recrée tout seul.
- **Recherche par type de crédit, sur un ou plusieurs clients** (onglet « Consommations par client ») :
  - « Types de crédit » : des mots libres, le nom du crédit contient l'un d'eux (PREVENTIVE ou ANTIVIRUS…), majuscules
    et accents ignorés ; les noms de crédit existants sont proposés.
  - « Entité » devient facultative et multiple : vide = toutes les entités visibles avec le profil, chaque entité
    choisie avec ses sous-entités. La liste « Crédits » suit les entités et les types sans recharger la page.
  - « Dates appliquées aux tickets » (comme avant) ou « aux crédits » : crédits dont la période recoupe les dates.
  - Bouton « Exporter les lignes » directement dans le formulaire (même fenêtre : tâches privées, XLSX ou CSV).
  - Le bouton « Exporter toutes les pages » au-dessus du tableau disparaît : « Exporter les lignes » fait le même
    export. L'export d'une sélection reste dans les actions de masse.
  - « Afficher autres tickets » et « Réaffecter crédit » restent pour une seule entité. Le nom du fichier exporté ne
    porte le client que si toutes les lignes sont du même client.
- **Recherches sauvegardées de GLPI** à la place des favoris maison : boutons « Enregistrer la recherche » et
  « Recherches sauvegardées », même fenêtre et même panneau que les listes de GLPI (privée ou publique, recherche par
  défaut avec l'étoile, aussi dans Outils → Recherches sauvegardées), sur « Consommations par client » et « Alertes de
  crédit ». Une recherche rouvre tout le formulaire. Les favoris existants sont repris à la mise à jour, en recherches
  privées de leur propriétaire ; la table des favoris disparaît. Le compteur du panneau est coupé pour ces recherches
  (GLPI ne sait pas compter les filtres du plugin).
- « Synthèse des crédits » : une recherche sauvegardée rouvre bien l'onglet (elle visait une page absente du plugin).

## 1.3.1 — 2026-11-02

**Après la mise à jour : cliquer « Mettre à jour » dans Configuration → Plugins.**

- Mails texte sans pièce jointe envoyés par la file d'attente de GLPI : plus d'avertissement « Undefined array
  key "attach_documents_to_notifications_for_anonymous" » quand ce réglage manque dans la base de GLPI (vu en
  production le 02/11/2026).
- CI GitHub : syntaxe sur PHP 8.3 à 8.5, installation réelle dans GLPI 12.0.1, chargement de chaque classe,
  parcours des pages et de leurs onglets.
- **Affichage** : le message « Élément modifié » après l'enregistrement de la configuration affichait du code HTML
  (`<span class="d-flex …">`) au lieu du nom.

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
