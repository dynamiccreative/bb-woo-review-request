# Changelog

## Non publié

- Un seul produit à noter : le bouton principal y mène et le produit est rappelé dessous, sans lien en double. Plusieurs produits : plus de bouton principal (il ne menait qu'au premier), un bouton « Noter ce produit » par ligne.
- Liste des produits aux couleurs de la charte de BB Woo Mail Layout (le lien prenait la couleur de base WooCommerce) ; vignettes 80 px recadrées côté serveur (`woocommerce_thumbnail`).
- Avec le pied de page de BB Woo Mail Layout, le lien d'opposition est affiché tout en bas de l'e-mail, dans le pied de page.

## 0.1.0

- Première version : e-mail WooCommerce « Demande d'avis » (`customer_review_request`), désactivé par défaut.
- Envoi planifié par Action Scheduler N jours après le passage en « Terminée » (7 par défaut), relance facultative ; conditions revérifiées à l'envoi (statut, opposition, produits restant à noter).
- Lien vers le formulaire d'avis des fiches produit (produits publiés, avis ouverts, variations regroupées, produits déjà notés exclus) ou vers une page d'avis externe (Google, Trustpilot…).
- Lien d'opposition signé dans chaque e-mail, avec confirmation, et désinscription en un clic (`List-Unsubscribe`, RFC 8058). Seule une empreinte HMAC de l'adresse est conservée.
- Exporteur et effaceur RGPD, texte suggéré pour la politique de confidentialité.
- Action « Envoyer la demande d'avis » sur la fiche commande ; note de commande à chaque envoi.
- Intégration à BB Woo Mail Layout 1.4.1+ (facultative) : mise en page du layout, intro et bouton dans son en-tête, intro modifiable dans son onglet E-mails.
