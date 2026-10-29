// Llamadas mínimas a la API de Stripe sin dependencias.
function flatten(obj, prefix, out) {
  out = out || new URLSearchParams();
  for (const [k, v] of Object.entries(obj)) {
    if (v === undefined || v === null || v === "") continue;
    const key = prefix ? `${prefix}[${k}]` : k;
    if (typeof v === "object") flatten(v, key, out);
    else out.append(key, String(v));
  }
  return out;
}

async function stripe(method, path, params) {
  const secret = process.env.STRIPE_SECRET_KEY;
  if (!secret) throw Object.assign(new Error("Falta STRIPE_SECRET_KEY en Vercel."), { status: 500 });
  const url = "https://api.stripe.com/v1/" + path + (method === "GET" && params ? "?" + flatten(params) : "");
  const res = await fetch(url, {
    method,
    headers: { Authorization: "Bearer " + secret, "Content-Type": "application/x-www-form-urlencoded" },
    body: method === "GET" ? undefined : flatten(params || {}),
  });
  const data = await res.json();
  if (!res.ok) throw Object.assign(new Error(data.error?.message || "Error de Stripe"), { status: 502 });
  return data;
}

module.exports = { stripe };
