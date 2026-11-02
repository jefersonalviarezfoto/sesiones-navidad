<?php
// POST /api/checkout — crea una sesión de Stripe Checkout por el depósito con tarjeta.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['error' => 'Método no permitido.']);
try {
  $body = json_decode(file_get_contents('php://input'), true);
  [$ok, $b] = validate_booking($body);
  if (!$ok) json_out(400, ['error' => $b]);
  $site = getenv('SITE_URL') ?: 'https://sesiones.alcaproducciones.com';
  $meta = [
    'pkg' => $b['pkg'], 'date' => $b['date'], 'time' => $b['time'], 'place' => $b['place'], 'dist' => $b['dist'],
    'name' => $b['name'], 'phone' => $b['phone'], 'email' => $b['email'], 'people' => (string) $b['people'],
    'address' => $b['address'], 'notes' => $b['notes'], 'total' => (string) $b['total'],
  ];
  $meta = array_filter($meta, fn($v) => $v !== '');
  $summary = $b['pkgName'] . ' · ' . $b['date'] . ' ' . $b['time'] . ' · ' . ($b['place'] === 'casa' ? 'En casa' : 'Estudio');
  $session = stripe_call('POST', 'checkout/sessions', [
    'mode' => 'payment',
    'locale' => 'es',
    'customer_email' => $b['email'],
    'success_url' => $site . '/?pago=ok&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $site . '/?pago=cancelado',
    'line_items' => [[
      'quantity' => 1,
      'price_data' => [
        'currency' => 'usd',
        'unit_amount' => card_deposit_cents(),
        'product_data' => [
          'name' => 'Depósito · ' . $b['pkgName'] . ' · Sesión Familiar de Navidad',
          'description' => $summary . '. Incluye ' . CARD_FEE_PCT . '% por pago con tarjeta. El depósito se descuenta del total.',
        ],
      ],
    ]],
    'metadata' => $meta,
    'payment_intent_data' => ['description' => 'Depósito Sesión Navidad · ' . $summary . ' · ' . $b['name'], 'metadata' => $meta],
  ]);
  json_out(200, ['url' => $session['url']]);
} catch (Throwable $e) {
  json_out(500, ['error' => 'No pudimos iniciar el pago. Intenta de nuevo o paga por Zelle.']);
}
