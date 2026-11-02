<?php
// GET /api/verify?session_id=cs_... — confirma con Stripe si el depósito se pagó.
require __DIR__ . '/_lib.php';
$id = (string) ($_GET['session_id'] ?? '');
if (!preg_match('/^cs_(live|test)_[A-Za-z0-9]+$/', $id)) json_out(400, ['paid' => false, 'error' => 'Sesión inválida.']);
try {
  $s = stripe_call('GET', 'checkout/sessions/' . $id, ['expand' => ['payment_intent']]);
  $paid = ($s['payment_status'] ?? '') === 'paid';
  $pi = $s['payment_intent'] ?? null;
  json_out(200, [
    'paid' => $paid,
    'amount' => ($s['amount_total'] ?? 0) / 100,
    'reference' => is_array($pi) ? $pi['id'] : $pi,
    'booking' => $paid ? ($s['metadata'] ?? []) : null,
  ]);
} catch (Throwable $e) {
  json_out(500, ['paid' => false, 'error' => 'No pudimos verificar el pago.']);
}
