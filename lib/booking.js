// Reglas del negocio que el servidor usa para validar y cobrar.
// Si cambias precios u horarios aquí, cámbialos también en CONFIG/PACKAGES de index.html.
const DEPOSIT_CENTS = 5000;      // $50.00
const CARD_FEE_PCT = 3.5;        // recargo por tarjeta
const TRAVEL_FEE = 75;           // casa a más de 10 millas de Miami
const FIRST_DATE = "2026-10-30";
const LAST_DATE = "2026-12-31";
const TIMES = ["10:00 AM", "12:00 PM", "2:00 PM", "4:00 PM", "6:00 PM"];
const PACKAGES = {
  esencial: { name: "Paquete Esencial", price: 149, max: 4 },
  clasico:  { name: "Paquete Clásico",  price: 229, max: 6 },
  heirloom: { name: "Paquete Heirloom", price: 349, max: 8 },
};

const cardDepositCents = () => Math.round(DEPOSIT_CENTS * (1 + CARD_FEE_PCT / 100));

const str = (v, max) => (typeof v === "string" ? v.trim().slice(0, max) : "");

function todayMiami() {
  // fecha de hoy en Miami, formato YYYY-MM-DD
  return new Intl.DateTimeFormat("en-CA", { timeZone: "America/New_York" }).format(new Date());
}

// Devuelve { ok, error, booking }
function validateBooking(b) {
  if (!b || typeof b !== "object") return { ok: false, error: "Datos de reserva inválidos." };
  const pkg = PACKAGES[b.pkg];
  if (!pkg) return { ok: false, error: "Paquete no válido." };
  const date = str(b.date, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < FIRST_DATE || date > LAST_DATE || date <= todayMiami())
    return { ok: false, error: "La fecha no está disponible." };
  const time = str(b.time, 10);
  if (!TIMES.includes(time)) return { ok: false, error: "La hora no está disponible." };
  const place = b.place === "casa" ? "casa" : "estudio";
  const dist = place === "casa" && b.dist === "lejos" ? "lejos" : "cerca";
  const name = str(b.name, 80), phone = str(b.phone, 25), email = str(b.email, 120);
  if (name.split(/\s+/).filter(Boolean).length < 2) return { ok: false, error: "Falta nombre y apellido." };
  if (phone.replace(/\D/g, "").length < 10) return { ok: false, error: "Teléfono inválido." };
  if (!/^\S+@\S+\.\S+$/.test(email)) return { ok: false, error: "Correo inválido." };
  const people = Math.max(1, Math.min(pkg.max, parseInt(b.people, 10) || 1));
  let address = "";
  if (place === "casa") {
    const addr = str(b.addr, 120), apt = str(b.apt, 40), city = str(b.city, 60), zip = str(b.zip, 5), access = str(b.access, 120);
    if (addr.length < 5 || city.length < 2 || !/^\d{5}$/.test(zip)) return { ok: false, error: "Dirección incompleta." };
    address = `${addr}${apt ? ", " + apt : ""}, ${city}, FL ${zip}${access ? " (" + access + ")" : ""}`;
  }
  const notes = str(b.notes, 300);
  const travel = dist === "lejos" ? TRAVEL_FEE : 0;
  return {
    ok: true,
    booking: { pkg: b.pkg, pkgName: pkg.name, price: pkg.price, travel, total: pkg.price + travel,
      date, time, place, dist, name, phone, email, people, address, notes },
  };
}

module.exports = { DEPOSIT_CENTS, CARD_FEE_PCT, cardDepositCents, validateBooking };
