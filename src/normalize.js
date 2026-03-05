export function normalizeDomain(host) {
  return host
    .toLowerCase()
    .replace(/^https?:\/\//, "")
    .replace(/:\d+$/, "")
    .replace(/^www\./, "");
}
