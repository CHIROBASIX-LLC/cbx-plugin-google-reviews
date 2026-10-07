=== CHIROBASIX Google Reviews Widget ===
Tags: google reviews, reviews, widget
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.9.1
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays Google Reviews as a floating widget with slide-out panel. Self-hosted Elfsight replacement.

== Description ==

A floating Google reviews badge with a slide-out panel, plus the `[cbx_google_reviews]` grid shortcode. Reviews are refreshed from Google twice a day and shown as visible page content.

= Structured data =

* The widget never prints star ratings or reviews in structured data (no `aggregateRating`, no `Review`).
* By default it prints no business entry either. The site's own SEO plugin or theme prints the office's business node, and each office should have exactly one.
* It still prints its business block when:
  * a `cbxr_schema` callback ties the block to the site's own node by setting a non-empty `@id`, or
  * the option `cbxr_keep_business_schema` is true (for example `wp option add cbxr_keep_business_schema 1 --autoload=yes`), or
  * the `cbxr_print_business_schema` filter returns true.
* When it prints, the block is the same as in 1.9.0, and nothing prints without a street address.

= Filters =

* `cbxr_schema` ( array $schema, string $place_id ): the business node before output. It still receives `aggregateRating` and `review`; both are removed from whatever it returns.
* `cbxr_print_business_schema` ( bool $print, array $schema, string $place_id ): whether the block prints. Since 1.9.1.
* `cbxr_business_name` ( string $name, string $google_name ): the business name used in the block.
* `cbxr_schema_review_limit` ( int $limit ): how many reviews are passed to `cbxr_schema`.
* `cbxr_max_display_reviews` and `cbxr_panel_initial_reviews`: how many reviews the panel shows in total and prints into the page.

== Changelog ==

= 1.9.1 =
* The widget no longer prints a second business entry (`LocalBusiness`) in structured data by default. The site's own business node is the only one.
* The block still prints when a `cbxr_schema` callback gives it a non-empty string `@id`, or when the site sets the option `cbxr_keep_business_schema` to true. New filter `cbxr_print_business_schema` overrides the decision in code.
* When the block prints, its content is unchanged from 1.9.0.
* No change to the badge, the panel, the review cards, the shortcode or the REST cards route.
* Added this readme.

= 1.9.0 =
* Removed star ratings and reviews from structured data: no `aggregateRating`, `review`, `reviews`, or any `Review` or `AggregateRating` node, at any depth, removed after the `cbxr_schema` filter.
* The business block (name, address, phone, geo, image, priceRange, url) still printed, in the same order as 1.8.2.
* `cbxr_schema`, `cbxr_schema_review_limit` and `cbxr_business_name` still fire with the same input as 1.8.2.
* No visible change for visitors.
