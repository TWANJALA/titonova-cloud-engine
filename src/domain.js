import dns from "dns/promises";

export function normalizeDomain(host) {
  return host
    .toLowerCase()
    .replace(/^https?:\/\//, "")
    .replace(/:\d+$/, "")
    .replace(/^www\./, "");
}

export async function verifyDomain(domain) {
  try {
    const records = await dns.resolveCname(domain);
    return records.some((r) => r.includes("titonova"));
  } catch {
    return false;
  }
}
