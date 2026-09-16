const express = require('express');
const router = express.Router();
const sharpRenderer = require('../services/sharpRenderer');
const { verifySecret } = require('../middleware/auth');
const axios = require('axios');

/**
 * POST /api/v1/render
 * Initiates render task for a custom order item.
 */
router.post('/render', verifySecret, async (req, res) => {
    try {
        const payload = req.body;
        if (!payload || !payload.order_id) {
            return res.status(422).json({
                success: false,
                error: 'Strict Data Contract Violation: order_id is required'
            });
        }

        const renderResult = await sharpRenderer.render(payload);

        // If a callback URL is provided, notify WordPress asynchronously
        if (payload.callback_url) {
            axios.post(payload.callback_url, {
                order_id: payload.order_id,
                item_id: payload.item_id,
                print_url: renderResult.url,
                dpi: 300,
                status: 'success'
            }, {
                headers: { 'X-POD-SECRET': process.env.SHARED_SECRET }
            }).catch(err => {
                console.error('Failed to dispatch webhook callback to WordPress:', err.message);
            });
        }

        return res.status(200).json({
            success: true,
            data: renderResult
        });
    } catch (error) {
        console.error('Error during render:', error);
        return res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

module.exports = router;
