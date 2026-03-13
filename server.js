const express = require('express');
const https = require('https');
const http = require('http');
const path = require('path');
const url = require('url');

const app = express();
const PORT = process.env.PORT || 3000;

// Serve static files from /public
app.use(express.static(path.join(__dirname, 'public')));

// =====================
// SERVER-SIDE CORS PROXY
// =====================
// This eliminates all CORS issues — the dashboard fetches from /api/proxy
// and the server fetches from the real API on its behalf.

app.get('/api/proxy', async (req, res) => {
  const targetUrl = req.query.url;
  if (!targetUrl) {
    return res.status(400).json({ error: 'Missing ?url= parameter' });
  }

  // Whitelist of allowed domains for security
  const ALLOWED_DOMAINS = [
    'query1.finance.yahoo.com',
    'query2.finance.yahoo.com',
    'api.stocklabs.com',
    'stocklabs.com',
    'www.financialjuice.com',
    'financialjuice.com',
    'nitter.privacydev.net',
    'rsshub.app',
    'api.rss2json.com',
    'www.google.com',
    'finance.google.com',
    'api.hyperliquid.xyz',
    'api.polymarket.com',
    'clob.polymarket.com',
    'gamma-api.polymarket.com',
    'www.predictit.org',
    'manifold.markets',
    'api.investing.com',
    'www.investing.com',
  ];

  try {
    const parsed = new URL(targetUrl);
    const hostname = parsed.hostname;

    if (!ALLOWED_DOMAINS.some(d => hostname === d || hostname.endsWith('.' + d))) {
      return res.status(403).json({ error: 'Domain not whitelisted: ' + hostname });
    }

    const protocol = parsed.protocol === 'https:' ? https : http;

    const proxyReq = protocol.get(targetUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.9',
      },
      timeout: 15000,
    }, (proxyRes) => {
      // Forward the response
      res.set('Access-Control-Allow-Origin', '*');
      res.set('Content-Type', proxyRes.headers['content-type'] || 'application/octet-stream');
      res.status(proxyRes.statusCode);

      let data = [];
      proxyRes.on('data', chunk => data.push(chunk));
      proxyRes.on('end', () => {
        res.send(Buffer.concat(data));
      });
    });

    proxyReq.on('error', (err) => {
      console.error('[PROXY ERROR]', targetUrl, err.message);
      res.status(502).json({ error: 'Proxy fetch failed: ' + err.message });
    });

    proxyReq.on('timeout', () => {
      proxyReq.destroy();
      res.status(504).json({ error: 'Proxy request timed out' });
    });

  } catch (err) {
    res.status(500).json({ error: 'Invalid URL or server error: ' + err.message });
  }
});

// POST proxy (for Hyperliquid API which uses POST)
app.post('/api/proxy', express.json(), async (req, res) => {
  const targetUrl = req.query.url;
  if (!targetUrl) {
    return res.status(400).json({ error: 'Missing ?url= parameter' });
  }

  try {
    const parsed = new URL(targetUrl);
    const protocol = parsed.protocol === 'https:' ? https : http;

    const postData = JSON.stringify(req.body);

    const options = {
      hostname: parsed.hostname,
      port: parsed.port,
      path: parsed.pathname + parsed.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData),
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
      },
      timeout: 15000,
    };

    const proxyReq = protocol.request(options, (proxyRes) => {
      res.set('Access-Control-Allow-Origin', '*');
      res.set('Content-Type', proxyRes.headers['content-type'] || 'application/json');
      res.status(proxyRes.statusCode);

      let data = [];
      proxyRes.on('data', chunk => data.push(chunk));
      proxyRes.on('end', () => {
        res.send(Buffer.concat(data));
      });
    });

    proxyReq.on('error', (err) => {
      res.status(502).json({ error: 'POST proxy failed: ' + err.message });
    });

    proxyReq.write(postData);
    proxyReq.end();

  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// CORS preflight
app.options('/api/proxy', (req, res) => {
  res.set('Access-Control-Allow-Origin', '*');
  res.set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.set('Access-Control-Allow-Headers', 'Content-Type');
  res.sendStatus(204);
});

// Health check
app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    uptime: process.uptime(),
    version: 'v21',
    timestamp: new Date().toISOString()
  });
});

// Fallback to index.html
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.listen(PORT, () => {
  console.log('');
  console.log('  ╔══════════════════════════════════════════╗');
  console.log('  ║   HORMUZ INTEL Dashboard Server v21      ║');
  console.log('  ║   Running on http://localhost:' + PORT + '        ║');
  console.log('  ║   Proxy endpoint: /api/proxy?url=...     ║');
  console.log('  ╚══════════════════════════════════════════╝');
  console.log('');
});
