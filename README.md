# BB Woo Review Request

Demande d'avis après achat pour WooCommerce. Plugin séparé de BB Woo Mail Layout : il fonctionne seul (gabarit WooCommerce standard) et prend automatiquement la mise en page bleuebuzz quand le layout est actif.

Hors périmètre, volontairement : e-mails marketing, paniers abandonnés, coupons contre avis, tracking d'ouverture ou de clic.

## Fonctionnement

| Élément | Détail |
|---|---|
| E-mail | `ReviewRequestEmail` (`customer_review_request`), classe `WC_Email` déclarée par `woocommerce_email_classes`. Réglages : WooCommerce → Réglages → E-mails → Demande d'avis. **Désactivé par défaut.** |
| Déclenchement | `woocommerce_order_status_changed` vers un statut de `bb_wrr_order_statuses` (`completed` par défaut) → action `bb_wrr_send` (groupe `bb-woo-review-request`) planifiée à J + délai. Une seule planification par commande (méta `_bb_wrr_scheduled`). Les commandes terminées avant l'activation ne sont pas rattrapées. |
| Envoi | Revérifie : e-mail activé, commande toujours au statut déclencheur, étape pas encore envoyée (`_bb_wrr_sent_1` / `_bb_wrr_sent_2`), client non opposé, au moins un produit à noter (sauf lien externe). Note de commande à chaque envoi. |
| Relance | Planifiée après la demande si « Relance (jours) » > 0. Avec les fiches produit, elle n'est pas envoyée si le client a tout noté entre-temps. |
| Produits | Lignes de la commande, variations regroupées sous le parent, produits publiés aux avis ouverts, avis WooCommerce activés. Option : exclure les produits déjà notés par l'adresse de facturation (avis publiés ou en attente). Lien : `permalien#reviews` (ouvre l'onglet Avis). Un seul produit : bouton principal vers sa fiche, produit rappelé dessous. Plusieurs : pas de bouton principal, un bouton « Noter ce produit » par ligne. |
| Lien externe | Page d'avis Google, Trustpilot, Avis Vérifiés… : un seul bouton, pas de liste de produits. |
| Opposition | `?bb_wrr_optout=<empreinte>&key=<clé>` : empreinte HMAC-SHA256 de l'adresse (jamais en clair), clé HMAC tronquée. GET → page de confirmation (les antivirus qui ouvrent les liens ne désinscrivent personne) ; POST → enregistrement. En-têtes `List-Unsubscribe` + `List-Unsubscribe-Post` (désinscription en un clic des clients mail, RFC 8058). Stockage : option `bb_wrr_optouts` (empreinte => date), non autochargée. |
| RGPD | Exporteur et effaceur WordPress (l'opposition est conservée à l'effacement, sinon les envois reprendraient), texte suggéré pour la politique de confidentialité. |
| Manuel | Action de commande « Envoyer la demande d'avis » (envoi immédiat, résultat en note de commande). |
| Désactivation | Envois planifiés annulés. Désinstallation : réglages et oppositions supprimés. |

## Avec BB Woo Mail Layout

Aucune dépendance : l'intégration (`Compat\MailLayout`) ne s'active que si la classe `BB\WooMailLayout\Plugin` existe. Nécessite le layout **1.4.1+** (filtre `bb_email_default_texts`).

- Le layout détecte l'e-mail comme tout e-mail d'extension et l'habille (activable dans son onglet E-mails).
- Intro : celle des réglages de l'e-mail, déclarée comme texte par défaut via `bb_email_default_texts`, donc modifiable aussi dans le layout. Relance : intro de relance, sauf intro personnalisée dans le layout.
- Bouton « Donner mon avis » via `bb_email_action_button`, sauf bouton réglé dans le layout.
- Intro et bouton sont alors affichés par l'en-tête du layout, le template n'affiche que la liste des produits.
- Couleurs de la liste (boutons, séparateurs) : celles de la charte du layout, sinon la couleur de base des e-mails WooCommerce.
- Lien d'opposition : ajouté en dernier au texte du pied de page du layout (`bb_email_footer_text`), donc tout en bas de l'e-mail. Si le pied de page du layout est masqué, il reste sous le contenu.
- Aperçu et e-mail de test du layout : fonctionnent avec une commande réelle ou fictive (produits calculés à la demande).

## Hooks publics

| Hook | Type | Rôle |
|---|---|---|
| `bb_wrr_order_statuses` | filtre `( string[] $statuses )` | Statuts déclencheurs, sans `wc-` (défaut `[ 'completed' ]`). |
| `bb_wrr_order_is_eligible` | filtre `( bool $eligible, WC_Order $order )` | Exclure des commandes (B2B, marketplace…). |
| `bb_wrr_products_to_review` | filtre `( WC_Product[] $products, WC_Order $order )` | Produits proposés (vide = pas d'envoi avec les fiches produit). |
| `bb_wrr_review_url` | filtre `( string $url, WC_Product $product )` | Lien d'avis d'un produit. |

## Points légaux (France)

- **Opposition** : lien dans chaque e-mail, sans connexion, effet immédiat.
- **Avis en ligne** (art. L111-7-2 et D111-17 du Code de la consommation, directive Omnibus) : si la boutique affiche les avis, elle doit indiquer s'ils sont vérifiés et comment. WooCommerce propose le badge « propriétaire vérifié » (Réglages → Produits → Avis).
- **Pas de contrepartie conditionnée à un avis positif.** Toute contrepartie à un avis doit être signalée : le plugin n'en propose volontairement aucune.

## Livraison

Zipper le dossier `bb-woo-review-request` et l'installer via Extensions → Ajouter. Les mises à jour suivantes passent par GitHub (`lib/GitHubUpdater.php`) : pousser une nouvelle `Version` dans l'en-tête du fichier principal, sur `main`.
