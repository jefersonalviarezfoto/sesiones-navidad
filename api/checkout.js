// POST /api/checkout — crea una sesión de Stripe Checkout por el depósito con tarjeta.
const { validateBooking, cardDepositCents, CARD_FEE_PCT } = require("../lib/booking");
const { stripe } = require("../lib/stripe");

module.exports = async (req, res) => {
  if (req.method !== "POST") return res.status(405).json({ error: "Método no permitido." });
  try {
    const body = typeof req.body === "string" ? JSON.parse(req.body || "{}") : req.body;
    const v = validateBooking(body);
    if (!v.ok) return res.status(400).json({ error: v.error });
    const b = v.booking;
    const site = process.env.SITE_URL || "https://sesiones.alcaproducciones.com";
    const meta = {
      pkg: b.pkg, date: b.date, time: b.time, place: b.place, dist: b.dist,
      name: b.name, phone: b.phone, email: b.email, people: String(b.people),
      address: b.address, notes: b.notes, total: String(b.total),
    };
    const summary = `${b.pkgName} · ${b.date} ${b.time} · ${b.place === "casa" ? "En casa" : "Estudio"}`;
    const session = await stripe("POST", "checkout/sessions", {
      mode: "payment",
      locale: "es",
      customer_email: b.email,
      success_url: `${site}/?pago=ok&session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${site}/?pago=cancelado`,
      line_items: { 0: {
        quantity: 1,
        price_data: {
          currency: "usd",
          unit_amount: cardDepositCents(),
          product_data: {
            name: `Depósito · ${b.pkgName} · Sesión Familiar de Navidad`,
            description: `${summary}. Incluye ${CARD_FEE_PCT}% por pago con tarjeta. El depósito se descuenta del total.`,
          },
        },
      } },
      metadata: meta,
      payment_intent_data: { description: `Depósito Sesión Navidad · ${summary} · ${b.name}`, metadata: meta },
    });
    return res.status(200).json({ url: session.url });
  } catch (e) {
    return res.status(e.status || 500).json({ error: "No pudimos iniciar el pago. Intenta de nuevo o paga por Zelle." });
  }
};
