require('dotenv').config();
const express = require('express');
const cors = require('cors');
const morgan = require('morgan');
const path = require('path');

const renderRouter = require('./routes/render');
const storageCleaner = require('./services/storageCleaner');

const app = express();
const PORT = process.env.PORT || 3001;

// Middlewares
app.use(cors());
app.use(express.json({ limit: '100mb' }));
app.use(express.urlencoded({ extended: true, limit: '100mb' }));
app.use(morgan('combined'));

// Protected Render Studio & QA Tool route (Must have ?secret=... from WordPress)
app.get(['/', '/test', '/test-render', '/test-render.html'], (req, res) => {
    const expectedSecret = process.env.SHARED_SECRET || 'pod_secret_token_123456';
    const providedSecret = req.query.secret;

    if (!providedSecret || providedSecret !== expectedSecret) {
        return res.status(403).send(`
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - POD Render Studio</title>
    <style>
        body {
            background: #0b0f19;
            color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .box {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 40px 32px;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 12px; color: #f87171; font-weight: 700; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 28px; }
        a.btn {
            background: #4f46e5;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            transition: background 0.2s;
        }
        a.btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">⛔</div>
        <h1>403 Forbidden</h1>
        <p>Truy cập trực tiếp công cụ Test Render bị khóa để bảo mật hệ thống.<br><br>Vui lòng mở công cụ này từ nút liên kết trong <strong>WordPress Admin &gt; Settings &gt; POD Customizer</strong>.</p>
        <a class="btn" href="http://pod.localhost/wp-admin/options-general.php?page=pod-customizer-settings">Về trang Cài đặt WordPress ↗</a>
    </div>
</body>
</html>
        `);
    }

    res.sendFile(path.join(__dirname, 'public', 'test-render.html'));
});

// Static serving for generated print files
const printsDir = process.env.OUTPUT_DIR || path.join(__dirname, '../storage/prints');
app.use('/prints', express.static(printsDir));
app.use(express.static(path.join(__dirname, 'public')));

// Health check
app.get('/health', (req, res) => {
    res.status(200).json({
        status: 'healthy',
        service: 'pod-backend-render-engine',
        timestamp: new Date().toISOString()
    });
});

// API Routes
app.use('/api/v1', renderRouter);

// Start server
app.listen(PORT, '0.0.0.0', () => {
    console.log(`[POD Backend] Render engine listening on http://0.0.0.0:${PORT}`);
    // Start automatic storage cleaner worker (runs every 30 minutes, deletes test & expired files)
    storageCleaner.startScheduledCleanup(30);
});
