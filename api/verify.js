// GET /api/verify?session_id=cs_... — confirma con Stripe si el depósito se pagó.
const { stripe } = require("../lib/stripe");

module.exports = async (req, res) => {
  const id = String(req.query.session_id || "");
  if (!/^cs_(live|test)_[A-Za-z0-9]+$/.test(id)) return res.status(400).json({ paid: false, error: "Sesión inválida." });
  try {
    const s = await stripe("GET", "checkout/sessions/" + id, { expand: { 0: "payment_intent" } });
    const paid = s.payment_status === "paid";
    res.setHeader("Cache-Control", "no-store");
    return res.status(200).json({
      paid,
      amount: s.amount_total / 100,
      reference: typeof s.payment_intent === "object" ? s.payment_intent.id : s.payment_intent,
      booking: paid ? s.metadata : null,
    });
  } catch (e) {
    return res.status(e.status || 500).json({ paid: false, error: "No pudimos verificar el pago." });
  }
};
