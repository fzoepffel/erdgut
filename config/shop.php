<?php

use App\Support\PickupLocations;

return [

    /*
    |--------------------------------------------------------------------------
    | General shop tunables
    |--------------------------------------------------------------------------
    |
    | The shop is the admin-managed successor to the hard-coded spargel
    | channel: a guest picks a delivery date, sees only the products the
    | inventory says are available that day, fills a cart, and pays via a
    | one-time SumUp checkout. Pricing comes straight from each Product's
    | `unit_price` (gross consumer price per canonical unit). Only the
    | cross-cutting scalars live here so they can be tuned without code.
    |
    */

    // Flat delivery fee (EUR) added once per order when fulfillment_mode
    // is "delivery". Pickup orders pay no fee. Mirrors the spargel fee.
    'delivery_fee_eur' => env('SHOP_DELIVERY_FEE_EUR', 2.90),

    // Reduced VAT rate for fresh produce in Germany — already included in
    // the gross unit prices, surfaced on receipts as an informational line.
    'vat_rate_percent' => env('SHOP_VAT_RATE_PERCENT', 7),

    // Quantity step (in canonical units) offered for mass/volume products
    // in the per-product picker — 50 g / 50 ml increments. Piece + bunch
    // products always step by 1 (whole counts).
    'quantity_step' => env('SHOP_QUANTITY_STEP', 50),

    // Largest amount (canonical units) the picker will offer for a single
    // mass/volume product line, capped further by remaining stock.
    'max_quantity' => env('SHOP_MAX_QUANTITY', 3000),

    // Largest count the picker offers for piece/bunch products, capped
    // further by remaining stock.
    'max_count' => env('SHOP_MAX_COUNT', 12),

    // How many weeks ahead the delivery-date picker scans for dates with
    // stock. Any date inside the horizon that has stock is selectable —
    // no hardcoded weekday filter (admin inflows drive which dates appear).
    'horizon_weeks' => env('SHOP_HORIZON_WEEKS', 8),

    /*
    |--------------------------------------------------------------------------
    | Company / seller details (for PDF receipts / Belege)
    |--------------------------------------------------------------------------
    |
    | These values appear on the Kleinbetragsrechnung that is generated after
    | each paid order (§ 33 UStDV). They are rendered in German regardless of
    | the customer's locale because the receipt is a German tax document.
    |
    */

    'company_name' => env('SHOP_COMPANY_NAME', 'Erdgut'),
    'company_street' => env('SHOP_COMPANY_STREET', ''),
    'company_postal_city' => env('SHOP_COMPANY_POSTAL_CITY', ''),
    'company_phone' => env('SHOP_COMPANY_PHONE', ''),
    'company_email' => env('SHOP_COMPANY_EMAIL', 'kontakt@erd-gut.de'),
    // Umsatzsteuer-Identifikationsnummer or Steuernummer of the seller.
    'company_vat_id' => env('SHOP_COMPANY_VAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Pickup locations
    |--------------------------------------------------------------------------
    |
    | Shared with the spargel flow via App\Support\PickupLocations so the
    | two channels can never offer different pickup points.
    |
    */

    'pickup_locations' => PickupLocations::all(),

];
