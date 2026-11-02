<?php
// Lógica compartida para el hosting con PHP (Spaceship / cPanel).
// Si cambias precios u horarios aquí, cámbialos también en index.html y en lib/booking.js.
const DEPOSIT_CENTS = 5000;   // $50.00
const CARD_FEE_PCT  = 3.5;    // recargo por tarjeta
const TRAVEL_FEE    = 75;     // casa a más de 10 millas de Miami
const FIRST_DATE    = '2026-10-30';
const LAST_DATE     = '2026-12-31';
const TIMES = ['10:00 AM', '12:00 PM', '2:00 PM', '4:00 PM', '6:00 PM'];
const PACKAGES = [
  'esencial' => ['name' => 'Paquete Esencial', 'price' => 149, 'max' => 4],
  'clasico'  => ['name' => 'Paquete Clásico',  'price' => 229, 'max' => 6],
  'heirloom' => ['name' => 'Paquete Heirloom', 'price' => 349, 'max' => 8],
];

function json_out($code, $data) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// La clave secreta se lee de stripe-config.php, que va FUERA de la carpeta pública:
// /home/TU_USUARIO/stripe-config.php  →  <?php return ['secret_key' => 'rk_live_...'];
function stripe_key() {
  $k = getenv('STRIPE_SECRET_KEY');
  if ($k) return $k;
  $candidates = [dirname(__DIR__, 2) . '/stripe-config.php', dirname(__DIR__, 3) . '/stripe-config.php'];
  foreach ($candidates as $f) {
    if (is_readable($f)) { $c = include $f; if (!empty($c['secret_key'])) return $c['secret_key']; }
  }
  return null;
}

function stripe_call($method, $path, $params = []) {
  $key = stripe_key();
  if (!$key) throw new Exception('Falta la clave de Stripe en stripe-config.php.', 500);
  $url = 'https://api.stripe.com/v1/' . $path;
  $ch = curl_init();
  if ($method === 'GET') {
    if ($params) $url .= '?' . http_build_query($params);
  } else {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
  }
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $key . ':',
    CURLOPT_TIMEOUT => 20,
  ]);
  $raw = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $data = json_decode($raw ?: '{}', true);
  if ($status < 200 || $status >= 300) throw new Exception($data['error']['message'] ?? 'Error de Stripe', 502);
  return $data;
}

function s($v, $max) { return is_string($v) ? mb_substr(trim($v), 0, $max) : ''; }

function validate_booking($b) {
  if (!is_array($b)) return [false, 'Datos de reserva inválidos.'];
  $pkgId = $b['pkg'] ?? '';
  if (!isset(PACKAGES[$pkgId])) return [false, 'Paquete no válido.'];
  $pkg = PACKAGES[$pkgId];
  $today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
  $date = s($b['date'] ?? '', 10);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < FIRST_DATE || $date > LAST_DATE || $date <= $today)
    return [false, 'La fecha no está disponible.'];
  $time = s($b['time'] ?? '', 10);
  if (!in_array($time, TIMES, true)) return [false, 'La hora no está disponible.'];
  $place = ($b['place'] ?? '') === 'casa' ? 'casa' : 'estudio';
  $dist = ($place === 'casa' && ($b['dist'] ?? '') === 'lejos') ? 'lejos' : 'cerca';
  $name = s($b['name'] ?? '', 80); $phone = s($b['phone'] ?? '', 25); $email = s($b['email'] ?? '', 120);
  if (count(preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY)) < 2) return [false, 'Falta nombre y apellido.'];
  if (strlen(preg_replace('/\D/', '', $phone)) < 10) return [false, 'Teléfono inválido.'];
  if (!preg_match('/^\S+@\S+\.\S+$/', $email)) return [false, 'Correo inválido.'];
  $people = max(1, min($pkg['max'], intval($b['people'] ?? 1)));
  $address = '';
  if ($place === 'casa') {
    $addr = s($b['addr'] ?? '', 120); $apt = s($b['apt'] ?? '', 40); $city = s($b['city'] ?? '', 60);
    $zip = s($b['zip'] ?? '', 5); $access = s($b['access'] ?? '', 120);
    if (mb_strlen($addr) < 5 || mb_strlen($city) < 2 || !preg_match('/^\d{5}$/', $zip)) return [false, 'Dirección incompleta.'];
    $address = $addr . ($apt ? ", $apt" : '') . ", $city, FL $zip" . ($access ? " ($access)" : '');
  }
  $travel = $dist === 'lejos' ? TRAVEL_FEE : 0;
  return [true, [
    'pkg' => $pkgId, 'pkgName' => $pkg['name'], 'price' => $pkg['price'], 'travel' => $travel,
    'total' => $pkg['price'] + $travel, 'date' => $date, 'time' => $time, 'place' => $place, 'dist' => $dist,
    'name' => $name, 'phone' => $phone, 'email' => $email, 'people' => $people,
    'address' => $address, 'notes' => s($b['notes'] ?? '', 300),
  ]];
}

function card_deposit_cents() { return (int) round(DEPOSIT_CENTS * (1 + CARD_FEE_PCT / 100)); }
