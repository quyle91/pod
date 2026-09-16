require('dotenv').config();
const express = require('express');
const cors = require('cors');
const morgan = require('morgan');
const path = require('path');

const renderRouter = require('./routes/render');

const app = express();
const PORT = process.env.PORT || 3001;

// Middlewares
app.use(cors());
app.use(express.json({ limit: '100mb' }));
app.use(express.urlencoded({ extended: true, limit: '100mb' }));
app.use(morgan('combined'));

// Static serving for generated print files
const printsDir = process.env.OUTPUT_DIR || path.join(__dirname, '../storage/prints');
app.use('/prints', express.static(printsDir));

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
});
