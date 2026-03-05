import express from "express";
import { getSiteByDomain } from "./domainResolver.js";

const app = express();

app.enable("trust proxy");

app.use((req, res, next) => {
  if (req.secure) return next();
  res.redirect("https://" + req.headers.host + req.url);
});

app.use(async (req, res, next) => {
  try {
    const host = req.headers.host;
    if (!host) return res.status(400).send("Invalid host");

    const site = await getSiteByDomain(host);

    if (!site) {
      return res.status(404).send("Site not found");
    }

    req.site = site;
    next();
  } catch (err) {
    console.error(err);
    res.status(500).send("Server error");
  }
});

app.get("*", (req, res) => {
  const site = req.site;

  const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>${site.config_json.title}</title>
<style>${site.css}</style>
</head>
<body>
${site.html}
</body>
</html>
`;

  res.send(html);
});

export default app;
