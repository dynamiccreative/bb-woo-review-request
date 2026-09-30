=== BB Woo Review Request ===
Contributors: bleuebuzz
Tags: woocommerce, avis, review, e-mail, français
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Demande d'avis après achat pour WooCommerce, en français, sans tracking.

== Description ==

Quelques jours après le passage d'une commande en « Terminée », le client reçoit un e-mail l'invitant à noter les produits achetés, avec une relance facultative.

* E-mail WooCommerce standard, réglable dans WooCommerce → Réglages → E-mails → Demande d'avis (désactivé par défaut).
* Délai d'envoi et relance en jours ; aucun envoi si la commande a été remboursée ou annulée entre-temps.
* Lien vers le formulaire d'avis de chaque produit (produits déjà notés exclus) ou vers votre page d'avis Google, Trustpilot, Avis Vérifiés…
* Lien d'opposition dans chaque e-mail et désinscription en un clic depuis Gmail, Outlook ou Apple Mail. Seule une empreinte de l'adresse est conservée.
* Export et effacement RGPD, texte suggéré pour la politique de confidentialité.
* Action « Envoyer la demande d'avis » sur la fiche commande.
* Mise en page automatique avec BB Woo Mail Layout (1.4.1 ou supérieur), sans en dépendre.
* Aucun appel externe, aucun pixel, aucun suivi de clic.

== Installation ==

1. Téléverser le zip dans Extensions → Ajouter, puis activer (WooCommerce doit être actif).
2. Régler et activer l'e-mail dans WooCommerce → Réglages → E-mails → Demande d'avis.
3. Tester depuis une commande : Actions de commande → Envoyer la demande d'avis.

== Frequently Asked Questions ==

= Les commandes passées avant l'activation reçoivent-elles la demande ? =

Non : seules les commandes terminées après l'activation de l'e-mail sont planifiées. Pour une commande antérieure, utilisez l'action de commande.

= Puis-je offrir un bon de réduction contre un avis ? =

Le plugin ne le propose volontairement pas : une contrepartie conditionnée à un avis positif est interdite, et toute contrepartie doit être signalée à côté de l'avis.

== Changelog ==

= 0.1.0 =
* Première version.
