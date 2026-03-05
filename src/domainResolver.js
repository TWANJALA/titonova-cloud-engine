import db from "./db.js";
import { normalizeDomain } from "./normalize.js";

export async function getSiteByDomain(host) {
  const domain = normalizeDomain(host);

  // 1️⃣ Custom domains first
  const custom = await db.query(
    `SELECT s.* FROM domains d
     JOIN sites s ON s.id = d.site_id
     WHERE d.domain = $1 AND d.status = 'active'
     LIMIT 1`,
    [domain]
  );

  if (custom.rows.length) return custom.rows[0];

  // 2️⃣ Subdomain fallback (*.titonova.app)
  if (domain.endsWith(".titonova.app")) {
    const slug = domain.split(".")[0];

    const sub = await db.query(
      `SELECT * FROM sites WHERE slug = $1 LIMIT 1`,
      [slug]
    );

    if (sub.rows.length) return sub.rows[0];
  }

  return null;
}
